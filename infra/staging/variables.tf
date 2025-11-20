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