FROM nginx

RUN apt-get update \
    && apt-get install -y vim

RUN rm /etc/nginx/conf.d/default.conf /etc/nginx/nginx.conf
COPY nginx.conf /etc/nginx/
COPY default.conf /etc/nginx/conf.d/
