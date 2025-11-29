###############################################################################
## WHAT IS THIS TERRAFORM DOING?
## - Creates a secured VPC with two subnets across two AZs (for HA/prod ready).
## - Creates security groups for ALB, ECS tasks, RDS, and Redis with explicit rules.
## - Creates ECR repos, SQS, S3 with versioning, CloudWatch log group.
## - Provisions private RDS MySQL and Redis (ElastiCache).
## - Creates Secrets Manager secrets for APP_KEY and DB password.
## - Creates an ECS cluster (services/tasks are defined in separate file).
## - IAM roles: Task execution role and Task role.
###############################################################################

terraform {
  required_version = ">= 1.6.0"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
  backend "s3" {
    bucket = "telkin-terraform-backend"
    key    = "staging/terraform.tfstate"
    region = "ap-southeast-2"
  }
}

provider "aws" {
  region = var.region
}

data "aws_caller_identity" "current" {}
data "aws_availability_zones" "available" {}

## ---------------------------
## VPC and networking
## ---------------------------
resource "aws_vpc" "main" {
  cidr_block           = "10.10.0.0/16"
  enable_dns_support   = true
  enable_dns_hostnames = true
  tags                 = { Name = "${var.project}-${var.env}-vpc" }
}

resource "aws_internet_gateway" "igw" {
  vpc_id = aws_vpc.main.id
  tags   = { Name = "${var.project}-${var.env}-igw" }
}

resource "aws_subnet" "public_1" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.10.0.0/24"
  availability_zone       = data.aws_availability_zones.available.names[0]
  map_public_ip_on_launch = true
  tags                    = { Name = "${var.project}-${var.env}-public1" }
}

resource "aws_subnet" "public_2" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.10.3.0/24"
  availability_zone       = data.aws_availability_zones.available.names[1]
  map_public_ip_on_launch = true
  tags                    = { Name = "${var.project}-${var.env}-public2" }
}

resource "aws_subnet" "private_1" {
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.10.1.0/24"
  availability_zone = data.aws_availability_zones.available.names[0]
  tags              = { Name = "${var.project}-${var.env}-priv1" }
}

resource "aws_subnet" "private_2" {
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.10.2.0/24"
  availability_zone = data.aws_availability_zones.available.names[1]
  tags              = { Name = "${var.project}-${var.env}-priv2" }
}

resource "aws_eip" "nat" {
  domain = "vpc"
  tags   = { Name = "${var.project}-${var.env}-nat-eip" }
}

resource "aws_nat_gateway" "natgw" {
  allocation_id = aws_eip.nat.id
  subnet_id     = aws_subnet.public_1.id
  tags          = { Name = "${var.project}-${var.env}-natgw" }
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.igw.id
  }

  tags = { Name = "${var.project}-${var.env}-publicrt" }
}

resource "aws_route_table_association" "public_1" {
  subnet_id      = aws_subnet.public_1.id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table_association" "public_2" {
  subnet_id      = aws_subnet.public_2.id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table" "private" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.natgw.id
  }

  tags = { Name = "${var.project}-${var.env}-privatert" }
}

resource "aws_route_table_association" "private_1" {
  subnet_id      = aws_subnet.private_1.id
  route_table_id = aws_route_table.private.id
}

resource "aws_route_table_association" "private_2" {
  subnet_id      = aws_subnet.private_2.id
  route_table_id = aws_route_table.private.id
}

## ---------------------------
## Security groups + rules (NO depends_on—let TF do it all!)
## ---------------------------
resource "aws_security_group" "alb" {
  name        = "${var.project}-${var.env}-alb-sg"
  description = "Public ALB ingress 80"
  vpc_id      = aws_vpc.main.id

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP from internet"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.project}-${var.env}-alb-sg" }
}

resource "aws_security_group" "ecs_tasks" {
  name        = "${var.project}-${var.env}-ecs-tasks-sg"
  description = "ECS tasks SG"
  vpc_id      = aws_vpc.main.id

  ## No inline ingress here; use explicit rule below
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.project}-${var.env}-ecs-tasks-sg" }
}

## Ingress from ALB to ECS tasks on 80 (nginx)
resource "aws_security_group_rule" "ecs_ingress_from_alb_80" {
  type                     = "ingress"
  from_port                = 80
  to_port                  = 80
  protocol                 = "tcp"
  security_group_id        = aws_security_group.ecs_tasks.id
  source_security_group_id = aws_security_group.alb.id
  description              = "ALB to ECS nginx"
}

resource "aws_security_group" "db" {
  name        = "${var.project}-${var.env}-db-sg"
  description = "RDS MySQL from ECS tasks"
  vpc_id      = aws_vpc.main.id

  ## No inline ingress; explicit rule below
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.project}-${var.env}-db-sg" }
}

## Allow ECS tasks to MySQL
resource "aws_security_group_rule" "db_ingress_from_ecs_3306" {
  type                     = "ingress"
  from_port                = 3306
  to_port                  = 3306
  protocol                 = "tcp"
  security_group_id        = aws_security_group.db.id
  source_security_group_id = aws_security_group.ecs_tasks.id
  description              = "ECS to RDS MySQL"
}

resource "aws_security_group" "redis" {
  name        = "${var.project}-${var.env}-redis-sg"
  description = "Redis from ECS tasks"
  vpc_id      = aws_vpc.main.id

  ## No inline ingress; explicit rule below
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${var.project}-${var.env}-redis-sg" }
}

## Allow ECS tasks to Redis
resource "aws_security_group_rule" "redis_ingress_from_ecs_6379" {
  type                     = "ingress"
  from_port                = 6379
  to_port                  = 6379
  protocol                 = "tcp"
  security_group_id        = aws_security_group.redis.id
  source_security_group_id = aws_security_group.ecs_tasks.id
  description              = "ECS to Redis"
}

## ---------------------------
## Secrets & IAM Roles
## ---------------------------
resource "aws_secretsmanager_secret" "app_key" {
  name = "${var.project}-${var.env}-app-key"
}
resource "aws_secretsmanager_secret_version" "app_key" {
  secret_id     = aws_secretsmanager_secret.app_key.id
  secret_string = var.app_key
}
resource "aws_secretsmanager_secret" "db_password" {
  name = "${var.project}-${var.env}-db-password"
}
resource "aws_secretsmanager_secret_version" "db_password" {
  secret_id     = aws_secretsmanager_secret.db_password.id
  secret_string = var.db_password
}

# OpenAI API key Secret (MUST be present as you reference this in ECS tasks)
resource "aws_secretsmanager_secret" "openai_api_key" {
  name = "${var.project}-${var.env}-openai-api-key"
}
resource "aws_secretsmanager_secret_version" "openai_api_key" {
  secret_id     = aws_secretsmanager_secret.openai_api_key.id
  secret_string = var.openai_api_key
}

# ECS Task Execution Role Assume Policy
data "aws_iam_policy_document" "ecs_task_execution_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals {
      type        = "Service"
      identifiers = ["ecs-tasks.amazonaws.com"]
    }
  }
}

# Custom Inline Policy for ECS Task Execution Role (example: S3, KMS, Secrets, SQS, etc)
data "aws_iam_policy_document" "ecs_task_execution" {
  statement {
    effect = "Allow"
    actions = [
      "logs:CreateLogStream",
      "logs:PutLogEvents",
      "logs:CreateLogGroup",
      "secretsmanager:GetSecretValue",
      "kms:Decrypt",
      "s3:PutObject",
      "s3:GetObject",
      "sqs:GetQueueUrl",
      "sqs:SendMessage",
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage"
    ]
    resources = ["*"]
  }
}

resource "aws_iam_role" "ecs_task_execution" {
  name               = "${var.project}-${var.env}-ecs-task-execution"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_execution_assume.json
  tags               = { Name = "${var.project}-${var.env}-ecs-task-execution" }
}

resource "aws_iam_role_policy" "ecs_execution" {
  name   = "ecsExecution"
  role   = aws_iam_role.ecs_task_execution.id
  policy = data.aws_iam_policy_document.ecs_task_execution.json
}

resource "aws_iam_role_policy_attachment" "ecs_exec_ecr_policy" {
  role       = aws_iam_role.ecs_task_execution.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy"
}

## ---------------------------
## ECR repositories
## ---------------------------
locals {
  repositories = [
    "laravel-php",
    "laravel-nginx",
    "agentic-orchestrator-service",
    "agentic-service",
    "databreach-event-advisor",
    "databreach-step1",
    "databreach-step2",
    "databreach-step3",
    "prod-agentic-orchestrator-service",
    "prod-db-privacy-discovery-service",
    "webhook-server"
  ]
}

resource "aws_ecr_repository" "repos" {
  for_each = toset(local.repositories)
  name     = "${var.project}-${var.env}-${each.key}"
  image_scanning_configuration { scan_on_push = true }
  lifecycle { prevent_destroy = false }
}

resource "aws_ecr_lifecycle_policy" "keep_two_images_per_repo" {
  for_each   = aws_ecr_repository.repos
  repository = each.value.name
  policy     = jsonencode({
    rules = [{
      rulePriority = 1
      description  = "Expire images, keep two most recent"
      selection    = { tagStatus = "any", countType = "imageCountMoreThan", countNumber = 2 }
      action       = { type = "expire" }
    }]
  })
}

## ---------------------------
## ECS cluster
## ---------------------------
resource "aws_ecs_cluster" "main" {
  name = "${var.project}-${var.env}-ecs-cluster"
}

## ---------------------------
## SQS
## ---------------------------
resource "aws_sqs_queue" "queue" {
  name = "${var.project}-${var.env}-queue"
}

## ---------------------------
## S3
## ---------------------------
resource "aws_s3_bucket" "assets" {
  bucket        = "${var.project}-${var.env}-assets"
  force_destroy = false
}

resource "aws_s3_bucket_versioning" "assets" {
  bucket = aws_s3_bucket.assets.id
  versioning_configuration { status = "Enabled" }
}

## ---------------------------
## CloudWatch Logs
## ---------------------------
resource "aws_cloudwatch_log_group" "laravel" {
  name              = "/ecs/${var.project}/${var.env}"
  retention_in_days = 30
}

## ---------------------------
## RDS (private)
## ---------------------------
resource "aws_db_subnet_group" "main" {
  name       = "${var.project}-${var.env}-dbsubnet"
  subnet_ids = [aws_subnet.private_1.id, aws_subnet.private_2.id]
}

resource "aws_db_instance" "main" {
  identifier              = "${var.project}-${var.env}-db"
  engine                  = "mysql"
  engine_version          = "8.0"
  instance_class          = "db.t3.micro"
  allocated_storage       = 20

  username = var.db_user
  password = var.db_password
  db_name  = var.db_name

  db_subnet_group_name   = aws_db_subnet_group.main.id
  vpc_security_group_ids = [aws_security_group.db.id]

  skip_final_snapshot = true
  publicly_accessible = false
}

## ---------------------------
## ElastiCache (Redis)
## ---------------------------
resource "aws_elasticache_subnet_group" "main" {
  name       = "${var.project}-${var.env}-redis-subnet"
  subnet_ids = [aws_subnet.private_1.id, aws_subnet.private_2.id]
}

resource "aws_elasticache_cluster" "main" {
  cluster_id           = "${var.project}-${var.env}-redis"
  engine               = "redis"
  engine_version       = "7.0"
  node_type            = "cache.t3.micro"
  num_cache_nodes      = 1
  parameter_group_name = "default.redis7"
  subnet_group_name    = aws_elasticache_subnet_group.main.id
  security_group_ids   = [aws_security_group.redis.id]
}

## ---------------------------
## IAM: ECS Task role (runtime access for app)
## ---------------------------
data "aws_iam_policy_document" "ecs_task_assume" {
  statement {
    actions = ["sts:AssumeRole"]
    principals { 
      type = "Service" 
      identifiers = ["ecs-tasks.amazonaws.com"] 
    }
  }
}

data "aws_iam_policy_document" "ecs_task_runtime" {
  statement {
    effect = "Allow"
    actions = [
      "s3:GetObject",
      "s3:PutObject",
      "s3:ListBucket",
      "sqs:GetQueueUrl",
      "sqs:SendMessage",
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage",
      "sqs:GetQueueAttributes",
      "secretsmanager:GetSecretValue"
    ]
    resources = ["*"]
  }
}

resource "aws_iam_role" "ecs_task" {
  name               = "${var.project}-${var.env}-ecs-task-role"
  assume_role_policy = data.aws_iam_policy_document.ecs_task_assume.json
}

resource "aws_iam_role_policy" "ecs_task_policy" {
  name   = "ecsTaskRuntime"
  role   = aws_iam_role.ecs_task.id
  policy = data.aws_iam_policy_document.ecs_task_runtime.json
}

resource "aws_iam_policy" "terraform_rds_eni_admin" {
  name        = "${var.project}-${var.env}-rds-eni-admin"
  description = "Policy granting Terraform EC2 ENI/RDS admin permissions for full lifecycle operations"

  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "ec2:DetachNetworkInterface",
          "ec2:DeleteNetworkInterface",
          "ec2:DescribeNetworkInterfaces",
          "ec2:ModifyNetworkInterfaceAttribute",
          "ec2:DescribeSecurityGroups",
          "ec2:DescribeVpcs",
          "rds:*"
        ]
        Resource = "*"
      }
    ]
  })
}

## ---------------------------
## Outputs
## ---------------------------
output "vpc_id"               { value = aws_vpc.main.id }
output "private_subnet_1"     { value = aws_subnet.private_1.id }
output "private_subnet_2"     { value = aws_subnet.private_2.id }
output "ecs_cluster_id"       { value = aws_ecs_cluster.main.id }
output "ecr_repo_urls"        { value = [for r in aws_ecr_repository.repos : r.repository_url] }
output "s3_bucket"            { value = aws_s3_bucket.assets.id }
output "rds_endpoint"         { value = aws_db_instance.main.endpoint }
output "redis_endpoint"       { value = aws_elasticache_cluster.main.cache_nodes[0].address }
output "sqs_queue_url"        { value = aws_sqs_queue.queue.id }
output "cloudwatch_log_group" { value = aws_cloudwatch_log_group.laravel.name }
output "alb_sg_id"            { value = aws_security_group.alb.id }
output "ecs_tasks_sg_id"      { value = aws_security_group.ecs_tasks.id }
output "db_sg_id"             { value = aws_security_group.db.id }
output "redis_sg_id"          { value = aws_security_group.redis.id }
output "secret_app_key"       { value = aws_secretsmanager_secret.app_key.arn }
output "secret_db_password"   { value = aws_secretsmanager_secret.db_password.arn }