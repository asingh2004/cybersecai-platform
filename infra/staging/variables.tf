###############################################################################
# INPUT VARIABLES
###############################################################################

variable "region" {
  description = "AWS region to deploy resources in"
  type        = string
  default     = "ap-southeast-2"
}

variable "project" {
  description = "Base project name used in resource names and tags"
  type        = string
}

variable "env" {
  description = "Environment name such as dev, staging, prod"
  type        = string
}

variable "db_name" {
  description = "Initial MySQL database to create in RDS"
  type        = string
}

variable "db_user" {
  description = "Master username for the RDS instance"
  type        = string
}

variable "db_password" {
  description = "Master password for RDS"
  type        = string
  sensitive   = true
}

variable "app_key" {
  description = "Laravel APP_KEY value to store in Secrets Manager"
  type        = string
  sensitive   = true
}

variable "openai_api_key" {
  description = "OpenAI API Key for FastAPI microservices"
  type        = string
  sensitive   = true
}

# List all microservices and make their image tag configurable
variable "agentic_orchestrator_image_tag" {
  description = "Tag for agentic orchestrator image"
  default = "latest"
}
variable "agentic_service_image_tag" {
  description = "Tag for agentic service image"
  default = "latest"
}
variable "databreach_event_advisor_image_tag" {
  description = "Tag for databreach event advisor image"
  default = "latest"
}
variable "databreach_step1_image_tag" {
  description = "Tag for databreach step 1 image"
  default = "latest"
}
variable "databreach_step2_image_tag" {
  description = "Tag for databreach step 2 image"
  default = "latest"
}
variable "databreach_step3_image_tag" {
  description = "Tag for databreach step 3 image"
  default = "latest"
}
variable "prod_agentic_orchestrator_service_image_tag" {
  description = "Tag for prod agentic orchestrator image"
  default = "latest"
}
variable "prod_db_privacy_discovery_service_image_tag" {
  description = "Tag for prod db privacy discovery image"
  default = "latest"
}
variable "webhook_server_image_tag" {
  description = "Tag for webhook server image"
  default = "latest"
}
variable "laravel_php_image_tag" {
  description = "Image tag to use for laravel-php ECS task definition"
  type        = string
  default     = "latest"
}
variable "laravel_nginx_image_tag" {
  description = "Image tag to use for laravel-nginx ECS task definition"
  type        = string
  default     = "latest"
}