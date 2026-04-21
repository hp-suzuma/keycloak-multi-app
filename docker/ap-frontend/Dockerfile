FROM node:22-alpine

WORKDIR /app

COPY docker/ap-frontend/init-ap-frontend.sh /usr/local/bin/init-ap-frontend

RUN chmod +x /usr/local/bin/init-ap-frontend

EXPOSE 3000

ENTRYPOINT ["/usr/local/bin/init-ap-frontend"]
