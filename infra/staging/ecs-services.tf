###############################################################################
# ECS TASK DEFINITIONS & SERVICES  – production ready for VPC from main.tf
###############################################################################

locals {
  common_log_options = {
    awslogs-group         = aws_cloudwatch_log_group.laravel.name
    awslogs-region        = var.region
    awslogs-stream-prefix = "ecs"
  }
}

###############################################################################
# ── LARAVEL PHP‑FPM
###############################################################################
resource "aws_ecs_task_definition" "laravel_php" {
  family                   = "${var.project}-${var.env}-laravel-php"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "laravel-php"
      image = "${aws_ecr_repository.repos["laravel-php"].repository_url}:latest"
      portMappings = [{ containerPort = 9000 }]
      essential = true
      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_user }
      ]
      secrets = [
        { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
        { name = "APP_KEY",     valueFrom = aws_secretsmanager_secret.app_key.arn }
      ]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "laravel_php" {
  name            = "${var.project}-${var.env}-laravel-php"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.laravel_php.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

###############################################################################
# ── LARAVEL NGINX
###############################################################################
resource "aws_ecs_task_definition" "laravel_nginx" {
  family                   = "${var.project}-${var.env}-laravel-nginx"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "laravel-nginx"
      image = "${aws_ecr_repository.repos["laravel-nginx"].repository_url}:latest"
      portMappings = [{ containerPort = 80 }]
      essential = true
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "laravel_nginx" {
  name            = "${var.project}-${var.env}-laravel-nginx"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.laravel_nginx.arn
  network_configuration {
    subnets         = [aws_subnet.public_1.id, aws_subnet.public_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
    assign_public_ip = true
  }
}

###############################################################################
# ── FASTAPI SERVICES (common pattern) – copy/modify block per‑service
###############################################################################

# Agentic Orchestrator
resource "aws_ecs_task_definition" "agentic_orchestrator_service" {
  family                   = "${var.project}-${var.env}-agentic-orchestrator"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  container_definitions = jsonencode([
    {
      name  = "agentic_orchestrator_service"
      image = "${aws_ecr_repository.repos["agentic_orchestrator_service"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","agentic_orchestrator_service:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "agentic_orchestrator_service" {
  name            = "${var.project}-${var.env}-agentic-orchestrator"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.agentic_orchestrator_service.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

# Agentic Service
resource "aws_ecs_task_definition" "agentic_service" {
  family                   = "${var.project}-${var.env}-agentic-service"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  container_definitions = jsonencode([
    {
      name  = "agentic_service"
      image = "${aws_ecr_repository.repos["agentic_service"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","agentic_service:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "agentic_service" {
  name            = "${var.project}-${var.env}-agentic-service"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.agentic_service.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

# Databreach Event Advisor
resource "aws_ecs_task_definition" "databreach_event_advisor" {
  family                   = "${var.project}-${var.env}-databreach-event-advisor"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  container_definitions = jsonencode([
    {
      name  = "databreach_event_advisor"
      image = "${aws_ecr_repository.repos["databreach_event_advisor"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","databreach_event_advisor:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "databreach_event_advisor" {
  name            = "${var.project}-${var.env}-databreach-event-advisor"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.databreach_event_advisor.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

# Databreach Step 1
resource "aws_ecs_task_definition" "databreach_step1" {
  family                   = "${var.project}-${var.env}-databreach-step1"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  container_definitions = jsonencode([
    {
      name  = "databreach_step1"
      image = "${aws_ecr_repository.repos["databreach_step1"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","databreach_step1:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "databreach_step1" {
  name            = "${var.project}-${var.env}-databreach-step1"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.databreach_step1.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

###############################################################################
# DATABREACH STEP 2
###############################################################################
resource "aws_ecs_task_definition" "databreach_step2" {
  family                   = "${var.project}-${var.env}-databreach-step2"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "databreach_step2"
      image = "${aws_ecr_repository.repos["databreach_step2"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","databreach_step2:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "databreach_step2" {
  name            = "${var.project}-${var.env}-databreach-step2"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.databreach_step2.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

###############################################################################
# DATABREACH STEP 3
###############################################################################
resource "aws_ecs_task_definition" "databreach_step3" {
  family                   = "${var.project}-${var.env}-databreach-step3"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "databreach_step3"
      image = "${aws_ecr_repository.repos["databreach_step3"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","databreach_step3:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "databreach_step3" {
  name            = "${var.project}-${var.env}-databreach-step3"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.databreach_step3.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

###############################################################################
# PROD AGENTIC ORCHESTRATOR SERVICE
###############################################################################
resource "aws_ecs_task_definition" "prod_agentic_orchestrator_service" {
  family                   = "${var.project}-${var.env}-prod-agentic-orchestrator"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "prod_agentic_orchestrator_service"
      image = "${aws_ecr_repository.repos["prod_agentic_orchestrator_service"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","main:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "prod_agentic_orchestrator_service" {
  name            = "${var.project}-${var.env}-prod-agentic-orchestrator"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.prod_agentic_orchestrator_service.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

###############################################################################
# PROD DB PRIVACY DISCOVERY SERVICE
###############################################################################
resource "aws_ecs_task_definition" "prod_db_privacy_discovery_service" {
  family                   = "${var.project}-${var.env}-prod-db-privacy-discovery"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "prod_db_privacy_discovery_service"
      image = "${aws_ecr_repository.repos["prod_db_privacy_discovery_service"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","db_privacy_discover:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "prod_db_privacy_discovery_service" {
  name            = "${var.project}-${var.env}-prod-db-privacy-discovery"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.prod_db_privacy_discovery_service.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

###############################################################################
# WEBHOOK SERVER
###############################################################################
resource "aws_ecs_task_definition" "webhook_server" {
  family                   = "${var.project}-${var.env}-webhook-server"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn

  container_definitions = jsonencode([
    {
      name  = "webhook_server"
      image = "${aws_ecr_repository.repos["webhook_server"].repository_url}:latest"
      portMappings = [{ containerPort = 8000 }]
      command = ["uvicorn","webhook_server:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "webhook_server" {
  name            = "${var.project}-${var.env}-webhook-server"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.webhook_server.arn
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_sg.id]
  }
}

# --- repeat pattern for databreach_step2, step3, prod_agentic_orchestrator_service,
#     prod_db_privacy_discovery_service, webhook_server ---
# For each: same network config, update the image and uvicorn command