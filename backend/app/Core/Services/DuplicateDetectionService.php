<?php

namespace App\Core\Services;

use App\Core\ValueObjects\DuplicateMatchResult;
use App\Core\ValueObjects\DuplicateSignals;
use App\Core\ValueObjects\IdentityDocumentReference;
use App\Core\ValueObjects\PersonName;

/**
 * The Duplicate-Detection Pattern (docs/DOMAIN_BLUEPRINT.md §6): fuzzy
 * matching plus human-in-the-loop review, built for Person but genuinely
 * domain-agnostic (Addendum C2 explicitly keeps this a generic Core
 * service, reusable for Vendor de-duplication in Inventory later) --
 * hence operating on the generic DuplicateSignals shape, never on
 * App\Modules\People\Models\Person directly.
 *
 * This is the scoring ALGORITHM only. Turning "these two rows share a
 * search_key prefix" into an actual candidate list to score is the
 * calling module's job (People queries its own `people` table);
 * whether a scored pair should be auto-flagged, shown for review, or
 * ignored is Identity Maintenance's job (Phase 3) -- this service never
 * mutates anything and never decides what happens with its own output.
 *
 * Scoring is deliberately structured so identity-document evidence is
 * REQUIRED to reach the 'certain' tier: name + DOB + nationality alone
 * (the exact signals two twins legitimately share) cap out below the
 * threshold. This isn't tuned by trial and error -- it's a structural
 * guarantee, see the weight constants below.
 */
class DuplicateDetectionService
{
    public const TIER_CERTAIN = 'certain';

    public const TIER_LIKELY = 'likely';

    public const TIER_NONE = 'none';

    private const FIRST_NAME_WEIGHT = 20;

    private const FAMILY_NAME_WEIGHT = 20;

    private const DOB_WEIGHT = 20;

    private const NATIONALITY_WEIGHT = 10;

    private const IDENTITY_DOCUMENT_WEIGHT = 30;

    /**
     * Max score reachable without any identity-document evidence:
     * 20 + 20 + 20 + 10 = 70, deliberately below HARD_DUPLICATE_THRESHOLD.
     */
    private const HARD_DUPLICATE_THRESHOLD = 80;

    private const REVIEW_THRESHOLD = 50;

    /**
     * A normalized, indexable key for narrowing a candidate search
     * (e.g. `WHERE search_key = ?`) before the real scoring in score()
     * runs -- never the final word on whether two records match.
     * Prefers the English name (consonant-skeleton, tolerant of
     * transliteration variance); falls back to normalized Arabic when no
     * English name is present.
     */
    public function computeSearchKey(PersonName $name): string
    {
        $first = $name->firstNameEn !== '' && $this->hasLatinLetters($name->firstNameEn)
            ? $this->consonantSkeleton($name->firstNameEn)
            : $this->normalizeArabic($name->firstNameAr);

        $family = $name->familyNameEn !== '' && $this->hasLatinLetters($name->familyNameEn)
            ? $this->consonantSkeleton($name->familyNameEn)
            : $this->normalizeArabic($name->familyNameAr);

        return trim("{$first}|{$family}");
    }

    public function score(DuplicateSignals $probe, DuplicateSignals $candidate): DuplicateMatchResult
    {
        $firstNameScore = (int) round(
            $this->fieldSimilarity(
                $probe->name->firstNameEn, $probe->name->firstNameAr,
                $candidate->name->firstNameEn, $candidate->name->firstNameAr,
            ) * self::FIRST_NAME_WEIGHT
        );

        $familyNameScore = (int) round(
            $this->fieldSimilarity(
                $probe->name->familyNameEn, $probe->name->familyNameAr,
                $candidate->name->familyNameEn, $candidate->name->familyNameAr,
            ) * self::FAMILY_NAME_WEIGHT
        );

        $dobScore = ($probe->dob !== null && $candidate->dob !== null && $probe->dob === $candidate->dob)
            ? self::DOB_WEIGHT : 0;

        $nationalityScore = ($probe->nationality !== null && $candidate->nationality !== null
                && strcasecmp($probe->nationality, $candidate->nationality) === 0)
            ? self::NATIONALITY_WEIGHT : 0;

        $identityDocumentScore = $this->hasMatchingIdentityDocument($probe->identityDocuments, $candidate->identityDocuments)
            ? self::IDENTITY_DOCUMENT_WEIGHT : 0;

        $total = $firstNameScore + $familyNameScore + $dobScore + $nationalityScore + $identityDocumentScore;

        $tier = match (true) {
            $total >= self::HARD_DUPLICATE_THRESHOLD => self::TIER_CERTAIN,
            $total >= self::REVIEW_THRESHOLD => self::TIER_LIKELY,
            default => self::TIER_NONE,
        };

        return new DuplicateMatchResult(
            score: $total,
            tier: $tier,
            breakdown: [
                'first_name' => $firstNameScore,
                'family_name' => $familyNameScore,
                'dob' => $dobScore,
                'nationality' => $nationalityScore,
                'identity_document' => $identityDocumentScore,
            ],
            subject: $candidate->subject,
        );
    }

    /**
     * Scores every candidate against the probe, returning only those
     * that reach at least TIER_LIKELY, ranked highest score first.
     *
     * @param  iterable<DuplicateSignals>  $candidates
     * @return DuplicateMatchResult[]
     */
    public function rank(DuplicateSignals $probe, iterable $candidates): array
    {
        $results = [];

        foreach ($candidates as $candidate) {
            $result = $this->score($probe, $candidate);

            if ($result->tier !== self::TIER_NONE) {
                $results[] = $result;
            }
        }

        usort($results, fn (DuplicateMatchResult $a, DuplicateMatchResult $b) => $b->score <=> $a->score);

        return $results;
    }

    /**
     * @param  IdentityDocumentReference[]  $a
     * @param  IdentityDocumentReference[]  $b
     */
    private function hasMatchingIdentityDocument(array $a, array $b): bool
    {
        foreach ($a as $docA) {
            foreach ($b as $docB) {
                if ($docA->equals($docB)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The best similarity across whichever language pair is actually
     * populated on both sides -- a match in either script counts.
     */
    private function fieldSimilarity(string $aEn, string $aAr, string $bEn, string $bAr): float
    {
        $scores = [];

        if (trim($aEn) !== '' && trim($bEn) !== '') {
            $scores[] = $this->nameSimilarity($aEn, $bEn);
        }

        if (trim($aAr) !== '' && trim($bAr) !== '') {
            $scores[] = $this->nameSimilarity($aAr, $bAr);
        }

        return $scores === [] ? 0.0 : max($scores);
    }

    /**
     * 1.0 for an exact normalized match; otherwise the best of a
     * Latin consonant-skeleton match (handles transliteration variance
     * like Mohammed/Muhammad/Mohamed collapsing to the same skeleton) or
     * a plain edit-distance ratio, computed multibyte-safe throughout so
     * Arabic script is compared correctly.
     */
    private function nameSimilarity(string $a, string $b): float
    {
        $a = trim($a);
        $b = trim($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($this->hasArabicLetters($a) || $this->hasArabicLetters($b)) {
            $normalizedA = $this->normalizeArabic($a);
            $normalizedB = $this->normalizeArabic($b);

            if ($normalizedA === $normalizedB) {
                return 1.0;
            }

            return $this->levenshteinRatio($normalizedA, $normalizedB);
        }

        if (mb_strtolower($a) === mb_strtolower($b)) {
            return 1.0;
        }

        $skeletonA = $this->consonantSkeleton($a);
        $skeletonB = $this->consonantSkeleton($b);

        if ($skeletonA !== '' && $skeletonA === $skeletonB) {
            return 1.0;
        }

        return max(
            $this->levenshteinRatio(mb_strtolower($a), mb_strtolower($b)),
            $this->levenshteinRatio($skeletonA, $skeletonB),
        );
    }

    /**
     * Strips everything but Latin letters, drops vowels, and collapses
     * doubled consecutive letters -- the specific normalization that
     * makes "Mohammed"/"Muhammad"/"Mohamed"/"Muhammed" collapse to the
     * identical skeleton "mhmd", since English transliterations of
     * Arabic names vary mainly in vowel choice, not consonants.
     */
    private function consonantSkeleton(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z]/', '', $s) ?? '';
        $s = preg_replace('/[aeiouy]/', '', $s) ?? '';
        $s = preg_replace('/(.)\1+/', '$1', $s) ?? '';

        return $s;
    }

    /**
     * Strips Arabic diacritics (tashkeel) and tatweel, and normalizes
     * alef/ya/ta-marbuta letter variants that are routinely interchanged
     * in casual data entry without representing a genuinely different
     * name.
     */
    private function normalizeArabic(string $s): string
    {
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s) ?? $s;
        $s = preg_replace('/[إأآا]/u', 'ا', $s) ?? $s;
        $s = str_replace('ى', 'ي', $s);
        $s = str_replace('ة', 'ه', $s);

        return trim($s);
    }

    private function hasArabicLetters(string $s): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $s);
    }

    private function hasLatinLetters(string $s): bool
    {
        return (bool) preg_match('/[a-zA-Z]/', $s);
    }

    /**
     * 1 - (edit distance / longer string length), computed on
     * multibyte-safe character arrays so Arabic (or any UTF-8 multibyte)
     * input isn't corrupted by PHP's byte-oriented levenshtein().
     */
    private function levenshteinRatio(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));

        return max(0.0, 1 - ($this->mbLevenshtein($a, $b) / $maxLen));
    }

    private function mbLevenshtein(string $a, string $b): int
    {
        $charsA = mb_str_split($a);
        $charsB = mb_str_split($b);
        $lenA = count($charsA);
        $lenB = count($charsB);

        $matrix = [];
        for ($i = 0; $i <= $lenA; $i++) {
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $lenB; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $lenA; $i++) {
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $charsA[$i - 1] === $charsB[$j - 1] ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost,
                );
            }
        }

        return $matrix[$lenA][$lenB];
    }
}
