<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- OpenGraph / Social Sharing -->
<meta property="og:url" content="{{ $shareLink ?? url('/') }}" />
<meta property="og:type" content="website" />
<meta property="og:title" content="{{ $title ?? 'cybersecai.io - Unified Sensitive Data Compliance & Security Automation' }}" />
<meta property="og:description" content="{{ $description ?? 'Automatically discover, classify, and risk-rate sensitive data across all your file stores. AI-powered, unified compliance, and audit-ready reporting—enterprise scale.' }}" />
<meta property="og:image" content="{{ asset('public/front/images/home/secure_data.svg') }}" />

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title ?? 'cybersecai.io - Unified Sensitive Data Compliance & Security Automation' }}">
<meta name="twitter:description" content="{{ $description ?? 'Discover, classify, and secure your sensitive information across cloud and on-prem—effortless compliance, every day.' }}">
<meta name="twitter:image" content="{{ asset('public/front/images/home/secure_data.svg') }}">

<!-- Favicon and mobile -->  
<link rel="icon" type="image/svg+xml" href="{{ asset('public/front/images/home/secure_data.svg') }}">
<link rel="shortcut icon" type="image/x-icon" href="{{ asset('public/front/images/home/secure_data.svg') }}">

<title>{{ $title ?? 'cybersecai.io - Unified Sensitive Data Compliance & Security Automation' }}{{ $additional_title ?? '' }}</title>

<!-- SEO -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="keywords" content="cybersecai, data compliance, sensitive information risk, PII automation, unified DLP, security automation, file risk rating, gdpr compliance, ai cybersecurity, audit automation, cloud compliance, data governance, privacy platform">
<meta name="description" content="{{ $description ?? 'cybersecai.io automatically discovers, classifies, and risk-rates sensitive data in the cloud and on-prem. Achieve continuous compliance and actionable audits with AI-driven automation and centralized dashboards.' }}">

<!-- Optional: Google site verification (update code if you need it for your domain) -->
<!-- <meta name="google-site-verification" content="PXEVPJ5Bxmk5R754fnXlYbUdn1PR5tKe7F5AndEuyOE" /> -->

<!-- CSS new version start-->
@stack('css')
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{asset('public/css/vendors/fontawesome/css/all.min.css')}}">
<link rel="stylesheet" href="{{asset('public/css/style.css')}}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<!--CSS new version end-->

</head>
<body>