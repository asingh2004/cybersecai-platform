<!DOCTYPE html>
@php
  $theme = 'light';
  if (Auth::check()) {
    $theme = Auth::user()->theme ?? (Session::get('theme') ?? 'light');
  } else {
    $theme = Session::get('theme') ?? 'light';
  }
@endphp
<html lang="en" data-bs-theme="{{ $theme }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Apply localStorage theme early for guests to reduce FOUC -->
  <script>
    (function() {
      try {
        var t = localStorage.getItem('theme');
        if (t && (!document.documentElement.getAttribute('data-bs-theme') || document.documentElement.getAttribute('data-bs-theme') === 'light')) {
          document.documentElement.setAttribute('data-bs-theme', t);
        }
      } catch (e) {}
    })();
  </script>

  <!-- OpenGraph / Social Sharing -->
  <meta property="og:url" content="{{ $shareLink ?? url('/') }}" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="{{ $title ?? 'mochanai.com - Unified Sensitive Data Compliance & Security Automation' }}" />
  <meta property="og:description" content="{{ $description ?? 'Automatically discover, classify, and risk-rate sensitive data across all your file stores. AI-powered, unified compliance, and audit-ready reporting—enterprise scale.' }}" />
  <meta property="og:image" content="{{ asset('public/front/images/home/secure_data.svg') }}" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $title ?? 'mochanai.com - Unified Sensitive Data Compliance & Security Automation' }}">
  <meta name="twitter:description" content="{{ $description ?? 'Discover, classify, and secure your sensitive information across cloud and on-prem—effortless compliance, every day.' }}">
  <meta name="twitter:image" content="{{ asset('public/front/images/home/secure_data.svg') }}">

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="{{ asset('public/front/images/home/secure_data.svg') }}">
  <link rel="shortcut icon" type="image/x-icon" href="{{ asset('public/front/images/home/secure_data.svg') }}">

  <title>{{ $title ?? 'mochanai.com - Unified Sensitive Data Compliance & Security Automation' }}{{ $additional_title ?? '' }}</title>

  <!-- SEO / Security -->
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="keywords" content="mochanai, data compliance, sensitive information risk, PII automation, unified DLP, security automation, file risk rating, gdpr compliance, ai cybersecurity, audit automation, cloud compliance, data governance, privacy platform">
  <meta name="description" content="{{ $description ?? 'mochanai.com automatically discovers, classifies, and risk-rates sensitive data in the cloud and on-prem. Achieve continuous compliance and actionable audits with AI-driven automation and centralized dashboards.' }}">

  <!-- Fonts -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Bootstrap 5.3.x -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

  <!-- Font Awesome (single include; v5) -->
  <link rel="stylesheet" href="{{ asset('public/css/vendors/fontawesome/css/all.min.css') }}">

  <!-- Minor dark mode tweaks for custom areas -->
  <style>
    [data-bs-theme="dark"] body {
      background-color: #0b1324;
      color: #e2e6ea;
    }
    [data-bs-theme="dark"] .header_area,
    [data-bs-theme="dark"] .footer-bg,
    [data-bs-theme="dark"] .modal-content {
      background-color: #0f1b2d !important;
      color: #e2e6ea;
    }
    [data-bs-theme="dark"] .dropdown-menu {
      --bs-dropdown-bg: #0f1b2d;
      --bs-dropdown-link-color: #e2e6ea;
    }
    [data-bs-theme="dark"] .card {
      background-color: #0f1b2d;
      color: #e2e6ea;
    }
    [data-bs-theme="dark"] a { color: #7cc7ff; }
    [data-bs-theme="dark"] a:hover { color: #9bd7ff; }
  </style>

  <!-- Site styles -->
  <link rel="stylesheet" href="{{ asset('public/css/style.css') }}">

  @stack('css')
  @stack('styles')
</head>
<body>