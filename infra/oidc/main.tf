terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.60"
    }
  }
}
provider "aws" {
  region = "ap-southeast-2" # or your preferred region
}

resource "aws_iam_openid_connect_provider" "github" {
  url = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]
  thumbprint_list = [
    "6938fd4d98bab03faadb97b34396831e3780aea1",
    "1b511abead59c6ce207077c0bf0e0043b1382612"
  ]
}

data "aws_caller_identity" "current" {}

data "aws_iam_policy_document" "gh_trust" {
  statement {
    actions = ["sts:AssumeRoleWithWebIdentity"]
    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }
    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }
	condition {
      test     = "StringLike"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["repo:asingh2004/cybersecai-platform:*"]
    }
  }
}

resource "aws_iam_role" "gh_actions" {
  name               = "your-saas-stg-gh-actions"
  assume_role_policy = data.aws_iam_policy_document.gh_trust.json
  max_session_duration = 21000
}



resource "aws_iam_role_policy_attachment" "gh_actions_admin" {
  role       = aws_iam_role.gh_actions.name
  policy_arn = "arn:aws:iam::aws:policy/AdministratorAccess"
}

output "gh_actions_role_arn" {
  value = aws_iam_role.gh_actions.arn
}