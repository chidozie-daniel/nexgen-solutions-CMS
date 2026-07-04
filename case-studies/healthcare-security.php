<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Hospital Data Security Overhaul | NexGen Solutions Case Study</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
      --dark-color: #0f172a;
      --secondary-color: #64748b;
      --accent-color: #0ea5e9;
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
      background-image: radial-gradient(circle at top right, rgba(14, 165, 233, 0.15), transparent),
                        url('https://images.unsplash.com/photo-1558494949-ef010cbdcc31?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80');
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
      color: #2563eb;
      display: block;
    }

    .section-title {
      color: #2563eb;
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
      color: #2563eb;
    }

    .tag {
      background: rgba(14, 165, 233, 0.1);
      color: #2563eb;
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
      <a href="../index.php#contact" class="btn btn-info text-white rounded-pill px-4">Enquire Now</a>
    </div>
  </div>
</nav>

<section class="hero-article">
  <div class="container">
    <div class="tag">Healthcare</div>
    <h1 class="display-3 mb-4">Hospital Data Security Overhaul</h1>
    <p class="lead opacity-75 mx-auto" style="max-width: 700px;">
      Securing patient data and ensuring mission-critical reliability for a distributed hospital network.
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
              <span class="stat-number">100%</span>
              <span class="text-secondary small fw-bold">HIPAA Compliance Rating</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-box">
              <span class="stat-number">24/7</span>
              <span class="text-secondary small fw-bold">Threat Monitoring</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-box">
              <span class="stat-number">5-Min</span>
              <span class="text-secondary small fw-bold">Incident Response Time</span>
            </div>
          </div>
        </div>

        <h3 class="section-title">
          <i class="bi bi-bullseye"></i> The Challenge
        </h3>
        <p class="mb-5">
          A regional hospital network with five locations was operating on outdated security protocols that left them vulnerable to ransomware and data breaches. Patient records were stored in a decentralized manner, making real-time access difficult for medical staff and creating multiple points of entry for potential attackers. With tightening HIPAA regulations and an increase in healthcare-targeted cyberattacks, the institution needed a complete security transformation.
        </p>

        <h3 class="section-title">
          <i class="bi bi-shield-lock"></i> Our Solution
        </h3>
        <p class="mb-4">
          NexGen Solutions implemented a Zero-Trust security model combined with high-performance data centralisation. Our comprehensive overhaul included:
        </p>
        <ul class="mb-5">
          <li><strong>Zero-Trust Architecture:</strong> Implemented strict access controls where no device or user is trusted by default, regardless of their location on the network.</li>
          <li><strong>End-to-End Encryption:</strong> Secured all patient data both at rest and in transit using advanced AES-256 encryption standards.</li>
          <li><strong>Centralized Data Hub:</strong> Consolidated patient records into a private, high-availability data center with real-time replication.</li>
          <li><strong>Managed Detection & Response (MDR):</strong> Deployed advanced threat hunting tools monitored by our security operations center 24/7.</li>
        </ul>

        <div class="p-4 bg-light rounded-4 mb-5 border-start border-4 border-info">
          <p class="fst-italic mb-0">
            "In healthcare, data security is literally a matter of life and death. NexGen Solutions didn't just give us a firewall; they gave us peace of mind and a platform that our doctors can depend on every second of the day."
          </p>
          <p class="small fw-bold mt-2 mb-0">— Director of Health IT, Metro Network</p>
        </div>

        <h3 class="section-title">
          <i class="bi bi-check-circle"></i> The Result
        </h3>
        <p>
          Post-implementation, the hospital network achieved a 100% compliance score in their annual external security audit. Attempted breaches are now detected and neutralized automatically within minutes. Medical staff reported a 20% improvement in record retrieval times due to the new centralized architecture, allowing for faster patient care. The network is now recognized as a regional leader in healthcare data technology and security.
        </p>
      </div>
    </div>
  </div>
</div>

<footer class="footer mt-5">
  <div class="container">
    <h3 class="mb-4">Protect Your Critical Data</h3>
    <p class="opacity-75 mb-4">Don't wait for a breach to happen. Partner with the experts in enterprise cybersecurity and mission-critical systems.</p>
    <a href="../index.php#contact" class="btn btn-warning btn-lg rounded-pill px-5 fw-bold">Contact Us Today</a>
    <div class="mt-5 opacity-50 small">
      © 2025 NexGen Solutions. All rights reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
