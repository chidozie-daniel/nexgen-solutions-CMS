<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Banking System Modernization | NexGen Solutions Case Study</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #00d2ff 0%, #0062ff 100%);
      --dark-color: #0f172a;
      --secondary-color: #64748b;
      --accent-color: #00d2ff;
      --glass-bg: rgba(255, 255, 255, 0.9);
    }
    
    body {
      font-family: 'Inter', sans-serif;
      color: #1e293b;
      line-height: 1.6;
      background-color: #f8fafc;
      background-image: url("https://www.transparenttextures.com/patterns/cubes.png");
    }

    h1, h2, h3, h4, h5, h6 {
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
    }

    .navbar {
      background: white;
      box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
      padding: 20px 0;
    }

    .navbar-brand {
      font-weight: 800;
      font-size: 1.5rem;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .hero-article {
      background: var(--dark-color);
      background-image: radial-gradient(circle at top right, rgba(0, 210, 255, 0.15), transparent),
                        url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80');
      background-size: cover;
      background-position: center;
      background-blend-mode: overlay;
      padding: 120px 0;
      color: white;
      text-align: center;
    }

    .article-content {
      background: white;
      border-radius: 24px;
      margin-top: -60px;
      padding: 60px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.05);
      position: relative;
      z-index: 10;
    }

    .stat-box {
      background: #f1f5f9;
      border-radius: 16px;
      padding: 30px;
      text-align: center;
      border: 1px solid #e2e8f0;
      height: 100%;
      transition: transform 0.3s;
    }

    .stat-box:hover {
      transform: translateY(-5px);
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 800;
      color: #0062ff;
      display: block;
    }

    .section-title {
      color: #0062ff;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .footer {
      background: var(--dark-color);
      color: white;
      padding: 60px 0;
      text-align: center;
    }
    
    .btn-back {
      color: #64748b;
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 30px;
      transition: color 0.2s;
    }

    .btn-back:hover {
      color: #0062ff;
    }

    .tag {
      background: rgba(0, 98, 255, 0.1);
      color: #0062ff;
      padding: 6px 16px;
      border-radius: 30px;
      font-size: 0.85rem;
      font-weight: 600;
      display: inline-block;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="../index.php">NexGen Solutions</a>
    <div class="ms-auto">
      <a href="../index.php#contact" class="btn btn-primary rounded-pill px-4">Get Started</a>
    </div>
  </div>
</nav>

<section class="hero-article">
  <div class="container">
    <div class="tag">Finance Sector</div>
    <h1 class="display-3 mb-4">Banking System Modernization</h1>
    <p class="lead opacity-75 mx-auto" style="max-width: 700px;">
      How NexGen Solutions revolutionized a regional bank's legacy infrastructure to deliver lightning-fast transaction speeds and enterprise-grade security.
    </p>
  </div>
</section>

<div class="container">
  <div class="row">
    <div class="col-lg-10 mx-auto">
      <div class="article-content">
        <a href="../index.php#cases" class="btn-back">
          <i class="bi bi-arrow-left"></i> Back to Case Studies
        </a>

        <div class="row g-4 mb-5">
          <div class="col-md-4">
            <div class="stat-box">
              <span class="stat-number">300%</span>
              <span class="text-secondary small fw-bold">Transaction Speed Increase</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-box">
              <span class="stat-number">40%</span>
              <span class="text-secondary small fw-bold">Operational Efficiency</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-box">
              <span class="stat-number">99.9%</span>
              <span class="text-secondary small fw-bold">System Uptime</span>
            </div>
          </div>
        </div>

        <h3 class="section-title">
          <i class="bi bi-bullseye"></i> The Challenge
        </h3>
        <p class="mb-5">
          The client, a prominent regional bank, was struggling with a fragmented legacy infrastructure that had been patched together over two decades. High latency in transaction processing was leading to customer frustration, while maintaining outdated hardware was consuming nearly 60% of their annual IT budget. Furthermore, evolving regulatory requirements demanded a level of data transparency and real-time monitoring that their existing systems simply couldn't provide.
        </p>

        <h3 class="section-title">
          <i class="bi bi-lightning-charge"></i> Our Solution
        </h3>
        <p class="mb-4">
          NexGen Solutions implemented a phased modernization strategy that completely overhauled the bank's core systems without interrupting daily operations. Our approach included:
        </p>
        <ul class="mb-5">
          <li><strong>Microservices Architecture:</strong> Decoupled monolithic systems into scalable, resilient microservices.</li>
          <li><strong>Cloud-Native Migration:</strong> Transitioned critical data workloads to a secure private cloud environment.</li>
          <li><strong>Real-Time APIs:</strong> Developed high-performance APIs for instant transaction validation and processing.</li>
          <li><strong>Advanced Analytics:</strong> Integrated an AI-driven fraud detection layer that analyzes patterns in real-time.</li>
        </ul>

        <div class="p-4 bg-light rounded-4 mb-5 border-start border-4 border-primary">
          <p class="fst-italic mb-0">
            "The modernization project wasn't just about speed; it was about future-proofing our institution. NexGen delivered a system that allows us to innovate as fast as the market demands."
          </p>
          <p class="small fw-bold mt-2 mb-0">— CTO, Regional Bank Partner</p>
        </div>

        <h3 class="section-title">
          <i class="bi bi-check-circle"></i> The Result
        </h3>
        <p>
          Within twelve months of full implementation, the bank saw a transformative impact across all departments. Transaction processing times dropped from an average of 1.2 seconds to less than 300ms. Operational costs were reduced by 35% through automation and retired legacy maintenance. Most importantly, customer satisfaction scores reached an all-time high, driven by the reliability and speed of the new digital banking experience.
        </p>
      </div>
    </div>
  </div>
</div>

<footer class="footer mt-5">
  <div class="container">
    <h3 class="mb-4">Ready to Modernize Your Operations?</h3>
    <p class="opacity-75 mb-4">Join hundreds of industry leaders who trust NexGen Solutions for their digital transformation journey.</p>
    <a href="../index.php#contact" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold">Contact Us Today</a>
    <div class="mt-5 opacity-50 small">
      © 2025 NexGen Solutions. All rights reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
