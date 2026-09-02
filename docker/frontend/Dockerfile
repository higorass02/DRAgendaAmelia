FROM node:22-alpine

RUN corepack enable

WORKDIR /app
RUN chown node:node /app

USER node

# Corepack faz cache em $HOME/.cache — precisa rodar como o mesmo usuário
# que vai executar "yarn watch" depois, senão baixa de novo em runtime.
RUN corepack prepare yarn@1.22.22 --activate

COPY --chown=node:node package*.json ./
RUN npm install

COPY --chown=node:node . .

EXPOSE 5173

CMD ["yarn", "watch"]
