FROM node:20-slim AS build

RUN apt-get update && apt-get install -y --no-install-recommends git ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

# Bakes the H5P core/editor client assets into the image at build time so
# the running container doesn't need outbound GitHub access.
RUN bash scripts/fetch-core.sh
RUN npm run build

FROM node:20-slim

RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
ENV NODE_ENV=production

COPY package.json package-lock.json* ./
RUN npm ci --omit=dev

COPY --from=build /app/build ./build
COPY --from=build /app/h5p ./h5p

RUN mkdir -p storage/libraries storage/content storage/temporary uploads

EXPOSE 8080

CMD ["node", "build/index.js"]
