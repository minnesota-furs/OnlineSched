FROM node:18 as build-stage
WORKDIR /app
# Create the structure
RUN mkdir -p /app/src

# Copy the front end source files
COPY ./package.json ./package-lock.json ./webpack.config.js /app/
COPY src/ /app/src/
# if needed copy assets
# COPY assets/ /app/assets/

# specif for this "Schedule"
RUN mkdir -p /app/schedule-support
COPY schedule-support/ /app/schedule-support/

# start build of files
RUN npm install && npm run build
RUN rm -fr /app/npm_modules

FROM scratch AS export-stage
COPY --from=build-stage /app/build /