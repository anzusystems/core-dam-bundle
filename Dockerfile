FROM anzusystems/php:3.5.0-php83-cli-vipsffmpeg
#
### Basic arguments and variables
ARG DOCKER_USER_ID
ARG DOCKER_GROUP_ID
#
### Create nonroot user with specified USER_ID and GROUP_ID and fix permissions
RUN create-user ${DOCKER_USER_ID} ${DOCKER_GROUP_ID}
#
### Run configuration
USER user
