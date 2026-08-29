# h5p-server

A dedicated H5P authoring, storage and playback service, built on the
official Lumi Education H5P Node.js packages
(`@lumieducation/h5p-server`, `@lumieducation/h5p-express`). Laravel never
parses or stores H5P content — it only talks to this service over HTTP (see
`App\Services\H5p\H5PService` in the main app) and stores a content id
reference on `lesson_blocks`.

## Local setup (without Docker)

```bash
npm install
npm run fetch-core          # clones the H5P core + editor client assets (git, no npm package exists for these)
cp .env.example .env
npm run install-content-types  # installs a starter set of content types from the official H5P Hub
npm run dev                 # http://127.0.0.1:8080
```

`H5P_SERVER_API_KEY` in this service's `.env` must match the same variable
in the main Laravel app's `.env` — it's a shared secret Laravel sends on
every server-to-server request.

## Docker

`docker compose up` from the repository root builds and starts this
service alongside Laravel, MySQL, Redis and Vite (see `docker-compose.yml`).
The H5P core/editor client assets are fetched at image build time (see
`Dockerfile`), so the running container needs no outbound GitHub access.

## REST API

All endpoints below (except `/health`) require an `X-H5P-Api-Key` header
matching `H5P_SERVER_API_KEY`.

- `GET /health` — liveness check.
- `GET /api/content` — list content (id, title, mainLibrary, language).
- `GET /api/content/:id` — single content's display metadata.
- `GET /api/content/editor-model` — editor bootstrap model for new content.
- `GET /api/content/:id/editor-model` — editor bootstrap model for existing content.
- `GET /api/content/:id/player-model` — player bootstrap model.
- `POST /api/content` — create content (`{ mainLibraryUbername, parameters, metadata }`).
- `PATCH /api/content/:id` — update content.
- `DELETE /api/content/:id` — delete content.
- `GET /api/content/:id/export` — download as a `.h5p` package.
- `POST /api/content/import` — import an existing `.h5p` package (multipart `file`).
- `GET /api/libraries` — installed libraries.
- `GET /api/libraries/hub` — the H5P Hub's content type catalogue.
- `POST /api/libraries/hub/:machineName/install` — install a content type from the Hub.

`/h5p/*` (mounted separately, protected by `CORS_ORIGIN` rather than the API
key) is the stock H5P ajax/core/editor/content-file router from
`@lumieducation/h5p-express` — this is what `<h5p-editor>`/`<h5p-player>`
(the browser-side web components) load directly, since the legacy H5P
client-side JS makes many small internal calls that aren't practical to
proxy through Laravel one by one.

## Known limitations

- `npm audit` reports vulnerabilities inside the official H5P packages' own
  dependency trees (`path-to-regexp`, `qs`, `jsonpath`/`underscore`) with no
  fix currently available upstream. Forking these official packages would
  defeat the point of using them; tracked as a known issue to revisit when
  upstream publishes fixes.
- Content is not scoped per-tutor — the H5P server has no concept of
  ownership. Laravel enforces authorization (does this tutor own the lesson
  block a piece of content is attached to) before ever handing the browser
  a content id; H5P content itself is a shared library across all tutors,
  the same way installed content-type libraries are shared platform-wide.
