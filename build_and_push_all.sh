#!/usr/bin/env bash
set -euo pipefail

### CONFIG
AWS_REGION="us-east-1"
PROJECT="myproject"
ENV="prod"

# Get AWS Account ID
AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)

# Repositories (must match Terraform locals)
SERVICES=(
  "laravel-php"
  "laravel-nginx"
  "agentic_orchestrator_service"
  "agentic_service"
  "databreach_event_advisor"
  "databreach_step1"
  "databreach_step2"
  "databreach_step3"
  "prod_agentic_orchestrator_service"
  "prod_db_privacy_discovery_service"
  "webhook_server"
)

### Authenticate Docker with ECR
aws ecr get-login-password --region "$AWS_REGION" | \
  docker login --username AWS --password-stdin "${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com"

### Loop over all services & build/push
for svc in "${SERVICES[@]}"; do
  REPO="${PROJECT}-${ENV}-${svc}"
  ECR_URL="${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com/${REPO}"
  
  echo "────────────────────────────────────────────"
  echo "Building and pushing: ${svc}"
  echo "Repository URL: ${ECR_URL}"
  echo "────────────────────────────────────────────"

  case $svc in
    laravel-php)
      docker build -t "${ECR_URL}:latest" -f Dockerfile.app --target php .
      ;;
    laravel-nginx)
      docker build -t "${ECR_URL}:latest" -f Dockerfile.app --target nginx .
      ;;
    *)
      # Default FastAPI services path guess
      PATH_DIR="$(find ./fastapi-services -type d -name ${svc%%:*} | head -1 || true)"
      if [ -z "$PATH_DIR" ]; then
        echo "⚠️  Path for ${svc} not found."
        continue
      fi
      docker build -t "${ECR_URL}:latest" -f "${PATH_DIR}/Dockerfile.fastapi" "${PATH_DIR}"
      ;;
  esac

  docker push "${ECR_URL}:latest"
done