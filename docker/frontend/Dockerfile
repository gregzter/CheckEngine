FROM node:20-alpine

WORKDIR /app

# Install pnpm
RUN npm install -g pnpm

EXPOSE 5173

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
