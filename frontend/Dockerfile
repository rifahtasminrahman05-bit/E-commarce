FROM node:22-alpine

WORKDIR /workspace

COPY package.json package-lock.json ./
COPY apps/storefront/package.json apps/storefront/package.json
COPY apps/admin/package.json apps/admin/package.json
RUN npm ci

COPY . .

ARG APP_NAME=storefront
ENV APP_NAME=${APP_NAME}
EXPOSE 3000
CMD ["sh", "-c", "npm run dev --workspace=\"$APP_NAME\" -- --hostname 0.0.0.0"]
