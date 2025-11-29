locals {
  common_log_options = {
    awslogs-group         = aws_cloudwatch_log_group.laravel.name
    awslogs-region        = var.region
    awslogs-stream-prefix = "ecs"
  }
}

# Public ALB
resource "aws_lb" "staging_nginx" {
  name               = "${var.project}-${var.env}-nginx-alb"
  internal           = false
  load_balancer_type = "application"
  subnets            = [aws_subnet.public_1.id, aws_subnet.public_2.id]
  security_groups    = [aws_security_group.alb.id]
}
resource "aws_lb_target_group" "staging_nginx_tg" {
  name        = "${var.project}-${var.env}-nginx-tg"
  port        = 80
  protocol    = "HTTP"
  vpc_id      = aws_vpc.main.id
  target_type = "ip"
  health_check {
    path                = "/"
    interval            = 30
    healthy_threshold   = 2
    unhealthy_threshold = 2
    timeout             = 5
    matcher             = "200-399"
  }
}
resource "aws_lb_listener" "staging_nginx_http" {
  load_balancer_arn = aws_lb.staging_nginx.arn
  port              = 80
  protocol          = "HTTP"
  default_action {
    type             = "forward"
    target_group_arn = aws_lb_target_group.staging_nginx_tg.arn
  }
}

# LARAVEL APP: nginx + php + webhook sidecar
resource "aws_ecs_task_definition" "laravel_app" {
  family                   = "${var.project}-${var.env}-laravel-app"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([
    {
      name  = "laravel-nginx"
      image = "${aws_ecr_repository.repos["laravel-nginx"].repository_url}:${var.laravel_nginx_image_tag}"
      portMappings = [{ containerPort = 80 }]
      essential   = true
      dependsOn = [{ containerName = "laravel-php", condition = "HEALTHY" }]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    },
    {
      name  = "laravel-php"
      image = "${aws_ecr_repository.repos["laravel-php"].repository_url}:${var.laravel_php_image_tag}"
      portMappings = [{ containerPort = 9000 }]
      essential = true
      healthCheck = {
        command     = ["CMD-SHELL", "pgrep php-fpm || exit 1"]
        interval    = 30
        timeout     = 5
        retries     = 3
        startPeriod = 10
      }
      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_user }
      ]
      secrets = [
        { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
        { name = "APP_KEY",     valueFrom = aws_secretsmanager_secret.app_key.arn },
        { name = "OPENAI_API_KEY", valueFrom = aws_secretsmanager_secret.openai_api_key.arn }
      ]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    },
    {
      name  = "webhook_server"
      image = "${aws_ecr_repository.repos["webhook-server"].repository_url}:${var.webhook_server_image_tag}"
      portMappings = [{ containerPort = 8000 }]
      essential    = false
      command      = ["uvicorn","webhook_server:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "laravel_app" {
  name            = "${var.project}-${var.env}-laravel-app"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.laravel_app.arn
  platform_version = "LATEST"

  network_configuration {
    subnets          = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups  = [aws_security_group.ecs_tasks.id]
    assign_public_ip = false
  }
  load_balancer {
    target_group_arn = aws_lb_target_group.staging_nginx_tg.arn
    container_name   = "laravel-nginx"
    container_port   = 80
  }

  deployment_controller { type = "ECS" }
  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }
  health_check_grace_period_seconds = 60
}

# AGENTIC APP
resource "aws_ecs_task_definition" "agentic_app" {
  family                   = "${var.project}-${var.env}-agentic-app"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([
    {
      name      = "prod_agentic_orchestrator_service"
      image     = "${aws_ecr_repository.repos["prod-agentic-orchestrator-service"].repository_url}:${var.prod_agentic_orchestrator_service_image_tag}"
      portMappings = [{ containerPort = 8000 }]
      command   = ["uvicorn","main:app","--host","0.0.0.0","--port","8000"]
      essential = true
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
      environment = [
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_user }
      ]
      secrets = [
        { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
        { name = "OPENAI_API_KEY", valueFrom = aws_secretsmanager_secret.openai_api_key.arn }
      ]
    },
    {
      name      = "agentic_orchestrator_service"
      image     = "${aws_ecr_repository.repos["agentic-orchestrator-service"].repository_url}:${var.agentic_orchestrator_image_tag}"
      portMappings = [{ containerPort = 8001 }]
      command   = ["uvicorn","agentic_orchestrator_service:app","--host","0.0.0.0","--port","8001"]
      essential = false
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
      environment = [
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_user }
      ]
      secrets = [
        { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
        { name = "OPENAI_API_KEY", valueFrom = aws_secretsmanager_secret.openai_api_key.arn }
      ]
    },
    {
      name      = "agentic_service"
      image     = "${aws_ecr_repository.repos["agentic-service"].repository_url}:${var.agentic_service_image_tag}"
      portMappings = [{ containerPort = 8002 }]
      command   = ["uvicorn","agentic_service:app","--host","0.0.0.0","--port","8002"]
      essential = false
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
      environment = [
        { name = "DB_HOST", value = aws_db_instance.main.address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = var.db_name },
        { name = "DB_USERNAME", value = var.db_user }
      ]
      secrets = [
        { name = "DB_PASSWORD", valueFrom = aws_secretsmanager_secret.db_password.arn },
        { name = "OPENAI_API_KEY", valueFrom = aws_secretsmanager_secret.openai_api_key.arn }
      ]
    }
  ])
}

resource "aws_ecs_service" "agentic_app" {
  name            = "${var.project}-${var.env}-agentic-app"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.agentic_app.arn
  platform_version = "LATEST"
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_tasks.id]
    assign_public_ip = false
  }

  deployment_controller { type = "ECS" }
  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }
}

# DATABREACH APP
resource "aws_ecs_task_definition" "databreach_app" {
  family                   = "${var.project}-${var.env}-databreach-app"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn

  container_definitions = jsonencode([
    {
      name      = "databreach_event_advisor"
      image     = "${aws_ecr_repository.repos["databreach-event-advisor"].repository_url}:${var.databreach_event_advisor_image_tag}"
      portMappings = [{ containerPort = 8000 }]
      command   = ["uvicorn","databreach_event_advisor:app","--host","0.0.0.0","--port","8000"]
      essential = true
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    },
    {
      name      = "databreach_step1"
      image     = "${aws_ecr_repository.repos["databreach-step1"].repository_url}:${var.databreach_step1_image_tag}"
      portMappings = [{ containerPort = 8001 }]
      command   = ["uvicorn","databreach_step1:app","--host","0.0.0.0","--port","8001"]
      essential = false
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    },
    {
      name      = "databreach_step2"
      image     = "${aws_ecr_repository.repos["databreach-step2"].repository_url}:${var.databreach_step2_image_tag}"
      portMappings = [{ containerPort = 8002 }]
      command   = ["uvicorn","databreach_step2:app","--host","0.0.0.0","--port","8002"]
      essential = false
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    },
    {
      name      = "databreach_step3"
      image     = "${aws_ecr_repository.repos["databreach-step3"].repository_url}:${var.databreach_step3_image_tag}"
      portMappings = [{ containerPort = 8003 }]
      command   = ["uvicorn","databreach_step3:app","--host","0.0.0.0","--port","8003"]
      essential = false
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}

resource "aws_ecs_service" "databreach_app" {
  name            = "${var.project}-${var.env}-databreach-app"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.databreach_app.arn
  platform_version = "LATEST"
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_tasks.id]
    assign_public_ip = false
  }
  deployment_controller { type = "ECS" }
  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }
}

# PROD DB PRIVACY DISCOVERY
resource "aws_ecs_task_definition" "prod_db_privacy_discovery_service" {
  family                   = "${var.project}-${var.env}-prod-db-privacy-discovery-service"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.ecs_task_execution.arn
  task_role_arn            = aws_iam_role.ecs_task.arn
  container_definitions = jsonencode([
    {
      name  = "prod_db_privacy_discovery_service"
      image = "${aws_ecr_repository.repos["prod-db-privacy-discovery-service"].repository_url}:${var.prod_db_privacy_discovery_service_image_tag}"
      portMappings = [{ containerPort = 8000 }]
      essential = true
      command   = ["uvicorn","db_privacy_discover:app","--host","0.0.0.0","--port","8000"]
      logConfiguration = { logDriver = "awslogs", options = local.common_log_options }
    }
  ])
}
resource "aws_ecs_service" "prod_db_privacy_discovery_service" {
  name            = "${var.project}-${var.env}-prod-db-privacy-discovery-service"
  cluster         = aws_ecs_cluster.main.id
  launch_type     = "FARGATE"
  desired_count   = 1
  task_definition = aws_ecs_task_definition.prod_db_privacy_discovery_service.arn
  platform_version = "LATEST"
  network_configuration {
    subnets         = [aws_subnet.private_1.id, aws_subnet.private_2.id]
    security_groups = [aws_security_group.ecs_tasks.id]
    assign_public_ip = false
  }
  deployment_controller { type = "ECS" }
  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }
}