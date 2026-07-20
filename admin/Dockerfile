# Development image for the admin SPA's Vite dev server. Built from the
# repo root (see docker-compose.yml's `context: .`), not admin/ alone,
# because admin/ and packages/contracts share one npm workspace rooted
# at the top-level package.json -- `npm install` has to run there for
# @alphaschool/contracts to resolve correctly.
#
# Source is bind-mounted at runtime, not baked in here; node_modules
# lives in a named volume (see docker-compose.yml) rather than a Windows
# bind mount, for the same I/O reasons as backend/vendor.
FROM node:22-bookworm-slim

WORKDIR /workspace

EXPOSE 5173

CMD ["npm", "run", "dev", "--workspace=admin", "--", "--host", "0.0.0.0"]
