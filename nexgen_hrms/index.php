<?php
// Add this at the VERY TOP of your index.php file
session_start();
require_once 'config/database.php';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name']) && isset($_POST['email'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';
    $service = $_POST['service'] ?? '';
    $message = $_POST['message'] ?? '';
    
    $conn = getDBConnection();
    
    // Save to inquiries table
    $sql = "INSERT INTO inquiries (name, email, phone, service, message, status) 
            VALUES (?, ?, ?, ?, ?, 'new')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $email, $phone, $service, $message);
    
    if ($stmt->execute()) {
        // Send email notification to HR
        $to = "hr@nexgensolutions.com";
        $subject = "New Inquiry from Website: $service";
        $email_message = "Name: $name\nEmail: $email\nPhone: $phone\nService: $service\n\nMessage:\n$message";
        $headers = "From: website@nexgensolutions.com";
        
        mail($to, $subject, $email_message, $headers);
        
        // Set success message
        $_SESSION['form_success'] = "Thank you! Your inquiry has been submitted. We'll contact you within 24 hours.";
    } else {
        $_SESSION['form_error'] = "Sorry, there was an error submitting your inquiry. Please try again.";
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF'] . '#contact');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NexGen Solutions | Technology Consulting</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  
  <style>
    :root {
      --primary-color: #0d6efd;
      --secondary-color: #6c757d;
      --accent-color: #ffc107;
      --dark-color: #212529;
      --light-color: #f8f9fa;
    }
    
    /* Custom Styles */
    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      padding-top: 76px;
      color: var(--dark-color);
      scroll-behavior: smooth;
    }
    
    /* Navbar */
    .navbar {
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      padding: 15px 0;
    }
    
    .navbar.scrolled {
      padding: 10px 0;
      background-color: rgba(255, 255, 255, 0.95) !important;
      backdrop-filter: blur(10px);
    }
    
    .navbar-brand {
      font-size: 1.8rem;
    }
    
    .navbar-nav .nav-link {
      font-weight: 500;
      margin: 0 5px;
      border-radius: 5px;
      padding: 8px 15px !important;
      transition: all 0.2s;
    }
    
    .navbar-nav .nav-link:hover {
      background-color: rgba(13, 110, 253, 0.1);
      color: var(--primary-color);
    }
    
    .navbar-nav .nav-link.active {
      background-color: rgba(13, 110, 253, 0.15);
      color: var(--primary-color);
    }
    
    /* Hero Section */
    .hero-section {
      background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
      padding: 120px 0 100px;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .hero-section::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80');
      background-size: cover;
      background-position: center;
      opacity: 0.1;
    }
    
    .hero-section h1 {
      font-size: 3.2rem;
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: 1.5rem;
    }
    
    @media (max-width: 768px) {
      .hero-section h1 {
        font-size: 2.5rem;
      }
    }
    
    /* Buttons */
    .btn-warning {
      font-weight: 600;
      padding: 12px 30px;
      border-radius: 8px;
      transition: all 0.3s;
      border: none;
    }
    
    .btn-warning:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
      background-color: #ffca2c;
    }
    
    .btn-primary {
      padding: 10px 30px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 15px rgba(13, 110, 253, 0.3);
    }
    
    /* Section Headers */
    .section-header {
      margin-bottom: 3rem;
      text-align: center;
    }
    
    .section-header h2 {
      font-weight: 800;
      color: var(--dark-color);
      position: relative;
      display: inline-block;
      padding-bottom: 15px;
    }
    
    .section-header h2::after {
      content: "";
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background-color: var(--accent-color);
      border-radius: 2px;
    }
    
    /* Feature Boxes */
    .feature-box {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
      height: 100%;
      transition: all 0.3s ease;
      border-top: 4px solid var(--primary-color);
      cursor: pointer;
    }
    
    .feature-box:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }
    
    .feature-box h5 {
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--primary-color);
    }
    
    .feature-box i {
      font-size: 2.5rem;
      margin-bottom: 1.5rem;
      color: var(--primary-color);
    }
    
    /* Service Cards */
    .service-card {
      background: white;
      padding: 2rem 1.5rem;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      height: 100%;
      transition: all 0.3s ease;
      border-left: 4px solid transparent;
      cursor: pointer;
    }
    
    .service-card:hover {
      border-left-color: var(--accent-color);
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .service-card h6 {
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 0.8rem;
    }
    
    /* Why Cards */
    .why-card {
      background: white;
      padding: 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      color: var(--dark-color);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      height: 100%;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100px;
      cursor: pointer;
    }
    
    .why-card:hover {
      background-color: var(--primary-color);
      color: white;
      transform: scale(1.05);
    }
    
    /* Testimonial */
    .testimonial-box {
      max-width: 800px;
      background: white;
      padding: 2.5rem;
      border-radius: 15px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
      position: relative;
      margin-top: 2rem;
    }
    
    .testimonial-box::before {
      content: "\201C";
      font-size: 6rem;
      color: rgba(13, 110, 253, 0.1);
      position: absolute;
      top: -30px;
      left: 20px;
      font-family: Georgia, serif;
    }
    
    /* Contact Section */
    .contact-section {
      background: linear-gradient(135deg, var(--dark-color) 0%, #343a40 100%);
      color: white;
      padding: 5rem 0;
    }
    
    .contact-form {
      max-width: 800px;
    }
    
    /* FIXED: Dropdown styles for contact form */
    .contact-form .form-control {
      padding: 15px;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      background-color: rgba(255, 255, 255, 0.05);
      color: white;
      transition: all 0.3s;
    }
    
    /* Fix for dropdown select options */
    .contact-form select.form-control {
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 16px 12px;
      padding-right: 2.5rem;
    }
    
    /* Make dropdown options visible with proper contrast */
    .contact-form select option {
      background-color: #212529;
      color: white;
      padding: 10px;
    }
    
    /* For Firefox */
    .contact-form select {
      color: white;
    }
    
    .contact-form select option:checked {
      background-color: #0d6efd;
      color: white;
    }
    
    .contact-form select option:hover,
    .contact-form select option:focus {
      background-color: #0d6efd;
      color: white;
    }
    
    /* Make placeholder text visible */
    .contact-form .form-control::placeholder {
      color: rgba(255, 255, 255, 0.6);
    }
    
    .contact-form .form-control:focus {
      background-color: rgba(255, 255, 255, 0.1);
      border-color: var(--accent-color);
      color: white;
      box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
    }
    
    /* Fix for selected option text color */
    .contact-form select:invalid {
      color: rgba(255, 255, 255, 0.6);
    }
    
    .contact-form select:valid {
      color: white;
    }
    
    /* Alternative solution: Custom dropdown */
    .custom-select-wrapper {
      position: relative;
    }
    
    .custom-select-wrapper select {
      cursor: pointer;
    }
    
    .form-control.is-invalid {
      border-color: #dc3545;
    }
    
    .form-control.is-valid {
      border-color: #198754;
    }
    
    .invalid-feedback {
      display: none;
      color: #dc3545;
      font-size: 0.875em;
      margin-top: 0.25rem;
    }
    
    .form-control.is-invalid ~ .invalid-feedback {
      display: block;
    }
    
    /* Footer */
    .footer {
      background-color: var(--dark-color);
      color: white;
      padding: 2rem 0;
    }
    
    .footer-links a {
      color: rgba(255, 255, 255, 0.7);
      text-decoration: none;
      margin: 0 10px;
      transition: color 0.3s;
    }
    
    .footer-links a:hover {
      color: var(--accent-color);
    }
    
    /* Animation for scroll reveal */
    .reveal {
      opacity: 0;
      transform: translateY(30px);
      transition: all 0.8s ease;
    }
    
    .reveal.active {
      opacity: 1;
      transform: translateY(0);
    }
    
    /* Case Studies */
    .case-study-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      height: 100%;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .case-study-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }
    
    .case-study-img {
      height: 200px;
      overflow: hidden;
    }
    
    .case-study-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.5s;
    }
    
    .case-study-card:hover .case-study-img img {
      transform: scale(1.05);
    }
    
    /* Stats Section */
    .stat-item {
      text-align: center;
      padding: 1.5rem;
    }
    
    .stat-number {
      font-size: 3rem;
      font-weight: 800;
      color: var(--primary-color);
      line-height: 1;
    }
    
    .stat-label {
      font-size: 1rem;
      color: var(--secondary-color);
      margin-top: 0.5rem;
    }
    
    /* Back to Top Button */
    .back-to-top {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 50px;
      height: 50px;
      background-color: var(--primary-color);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      opacity: 0;
      transform: translateY(100px);
      transition: all 0.3s ease;
      z-index: 1000;
      box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }
    
    .back-to-top.show {
      opacity: 1;
      transform: translateY(0);
    }
    
    .back-to-top:hover {
      background-color: #0b5ed7;
      transform: translateY(-3px);
    }
    
    /* Toast Notification */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
    }
    
    .toast {
      background-color: white;
      border-left: 4px solid var(--primary-color);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    /* Loading Spinner */
    .spinner {
      display: none;
      width: 2rem;
      height: 2rem;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

<!-- ================= NAVBAR ================= -->
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#home">
      <span class="text-primary">Nex</span>Gen Solutions
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link active" href="#home">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="#cases">Case Studies</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-lg-3">
            <!-- Add this to your navbar menu -->
    <a href="login.php" class="btn btn-outline-primary px-4">
        <i class="bi bi-box-arrow-in-right"></i> Employee Login
    </a>
          <button class="btn btn-primary px-4" id="getQuoteBtn">Get a Quote</button>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ================= HERO ================= -->
<section id="home" class="hero-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 text-white reveal">
        <h1 class="fw-bold mb-4">
          Innovative Technology Solutions for Your Business Growth
        </h1>
        <p class="lead mb-4">
          We empower organizations to thrive through cutting-edge consulting, cloud solutions, cybersecurity, and data-driven strategies. Let us transform your technology landscape.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="#services" class="btn btn-warning btn-lg">Our Services</a>
          <button class="btn btn-outline-light btn-lg" id="heroQuoteBtn">Get Free Quote</button>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block reveal">
        <div class="position-relative">
          <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80"
               class="img-fluid rounded-3 shadow-lg" alt="Team working on technology solutions">
          <div class="position-absolute bottom-0 start-0 bg-primary text-white p-3 rounded-end" style="border-left: 5px solid var(--accent-color);">
            <h5 class="mb-0">25+ Years of Experience</h5>
            <p class="mb-0">Trusted by 500+ companies</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= STATS ================= -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="row">
      <div class="col-md-3 col-6 stat-item">
        <div class="stat-number" data-count="500">0</div>
        <div class="stat-label">Satisfied Clients</div>
      </div>
      <div class="col-md-3 col-6 stat-item">
        <div class="stat-number" data-count="120">0</div>
        <div class="stat-label">Projects Completed</div>
      </div>
      <div class="col-md-3 col-6 stat-item">
        <div class="stat-number" data-count="50">0</div>
        <div class="stat-label">Expert Consultants</div>
      </div>
      <div class="col-md-3 col-6 stat-item">
        <div class="stat-number">24/7</div>
        <div class="stat-label">Support Available</div>
      </div>
    </div>
  </div>
</section>

<!-- ================= ABOUT ================= -->
<section id="about" class="py-5">
  <div class="container">
    <div class="section-header reveal">
      <h2>Who We Are</h2>
      <p class="lead text-muted">Driving digital transformation since 1999</p>
    </div>
    
    <div class="row align-items-center">
      <div class="col-lg-6 mb-4 mb-lg-0 reveal">
        <div class="position-relative">
          <img src="https://images.unsplash.com/photo-1556761175-4b46a572b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80"
               class="img-fluid rounded-3 shadow" alt="Office meeting">
          <div class="position-absolute top-0 start-0 bg-warning text-dark p-3 m-3 rounded">
            <h5 class="mb-0">Trusted Partner</h5>
          </div>
        </div>
      </div>
      <div class="col-lg-6 reveal">
        <h3 class="fw-bold mb-4">Your Partner in Digital Innovation</h3>
        <p class="lead mb-4">
          NexGen Solutions is a leading technology consulting firm focused on helping businesses modernize their IT infrastructure, improve security, and leverage data for competitive advantage.
        </p>
        <p class="mb-4">
          We partner with organizations of all sizes to design, build, and manage scalable technology solutions that drive growth, efficiency, and innovation. Our team of experts combines deep industry knowledge with cutting-edge technical skills.
        </p>
        <div class="d-flex align-items-center">
          <div class="me-4">
            <h4 class="text-primary mb-0">25+</h4>
            <p class="text-muted mb-0">Years Experience</p>
          </div>
          <div class="me-4">
            <h4 class="text-primary mb-0">98%</h4>
            <p class="text-muted mb-0">Client Retention</p>
          </div>
          <div>
            <button class="btn btn-primary" id="aboutQuoteBtn">Get Free Consultation</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= FEATURES ================= -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="section-header reveal">
      <h2>Our Core Expertise</h2>
      <p class="lead text-muted">Areas where we excel and deliver exceptional results</p>
    </div>
    
    <div class="row g-4">
      <div class="col-md-4 reveal">
        <div class="feature-box text-center" data-service="IT Consulting">
          <i class="bi bi-gear-fill"></i>
          <h5>IT Consulting</h5>
          <p>Strategic guidance to align technology with business goals and maximize ROI on tech investments.</p>
        </div>
      </div>
      <div class="col-md-4 reveal">
        <div class="feature-box text-center" data-service="Cloud Services">
          <i class="bi bi-cloud-fill"></i>
          <h5>Cloud Services</h5>
          <p>Secure, scalable cloud infrastructure design, migration, and management across all major platforms.</p>
        </div>
      </div>
      <div class="col-md-4 reveal">
        <div class="feature-box text-center" data-service="Cybersecurity">
          <i class="bi bi-shield-check"></i>
          <h5>Cybersecurity</h5>
          <p>End-to-end protection against digital threats with proactive monitoring and rapid incident response.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= SERVICES ================= -->
<section id="services" class="py-5">
  <div class="container">
    <div class="section-header reveal">
      <h2>Our Services</h2>
      <p class="lead text-muted">Comprehensive solutions tailored to your business needs</p>
    </div>

    <div class="row g-4">
      <div class="col-md-6 col-lg-3 reveal">
        <div class="service-card text-center" data-service="Digital Transformation">
          <i class="bi bi-lightning-charge-fill text-primary mb-3" style="font-size: 2rem;"></i>
          <h6>Digital Transformation</h6>
          <p>Modernizing workflows, systems, and processes to increase efficiency and competitiveness.</p>
          <a href="#contact" class="text-primary small fw-bold text-decoration-none">Get Quote →</a>
        </div>
      </div>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="service-card text-center" data-service="Managed IT Services">
          <i class="bi bi-headset text-primary mb-3" style="font-size: 2rem;"></i>
          <h6>Managed IT Services</h6>
          <p>Comprehensive 24/7 monitoring, maintenance, and support for your IT infrastructure.</p>
          <a href="#contact" class="text-primary small fw-bold text-decoration-none">Get Quote →</a>
        </div>
      </div>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="service-card text-center" data-service="Software Development">
          <i class="bi bi-code-slash text-primary mb-3" style="font-size: 2rem;"></i>
          <h6>Software Development</h6>
          <p>Custom enterprise-grade applications built with modern frameworks and methodologies.</p>
          <a href="#contact" class="text-primary small fw-bold text-decoration-none">Get Quote →</a>
        </div>
      </div>
      <div class="col-md-6 col-lg-3 reveal">
        <div class="service-card text-center" data-service="Data Analytics">
          <i class="bi bi-bar-chart-fill text-primary mb-3" style="font-size: 2rem;"></i>
          <h6>Data Analytics</h6>
          <p>Actionable insights from your data to drive smarter business decisions and strategies.</p>
          <a href="#contact" class="text-primary small fw-bold text-decoration-none">Get Quote →</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= CASE STUDIES ================= -->
<section id="cases" class="py-5 bg-light">
  <div class="container">
    <div class="section-header reveal">
      <h2>Case Studies</h2>
      <p class="lead text-muted">Real-world examples of how we've helped businesses succeed</p>
    </div>
    
    <div class="row g-4">
      <div class="col-md-4 reveal">
        <div class="case-study-card" data-case="banking">
          <div class="case-study-img">
            <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Finance technology">
          </div>
          <div class="p-4">
            <span class="badge bg-primary mb-2">Finance Sector</span>
            <h5>Banking System Modernization</h5>
            <p class="text-muted">Helped a regional bank modernize their core systems, improving transaction speed by 300%.</p>
            <button class="btn btn-link text-primary text-decoration-none fw-bold p-0">Read Full Case Study →</button>
          </div>
        </div>
      </div>
      <div class="col-md-4 reveal">
        <div class="case-study-card" data-case="retail">
          <div class="case-study-img">
            <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Retail technology">
          </div>
          <div class="p-4">
            <span class="badge bg-success mb-2">Retail</span>
            <h5>E-commerce Platform Scaling</h5>
            <p class="text-muted">Scaled an online retailer's platform to handle 5x traffic during peak seasons.</p>
            <button class="btn btn-link text-primary text-decoration-none fw-bold p-0">Read Full Case Study →</button>
          </div>
        </div>
      </div>
      <div class="col-md-4 reveal">
        <div class="case-study-card" data-case="healthcare">
          <div class="case-study-img">
            <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80" alt="Healthcare technology">
          </div>
          <div class="p-4">
            <span class="badge bg-info mb-2">Healthcare</span>
            <h5>Hospital Data Security Overhaul</h5>
            <p class="text-muted">Implemented comprehensive cybersecurity for a hospital network, ensuring HIPAA compliance.</p>
            <button class="btn btn-link text-primary text-decoration-none fw-bold p-0">Read Full Case Study →</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= WHY US ================= -->
<section class="py-5">
  <div class="container">
    <div class="section-header reveal">
      <h2>Why Choose NexGen Solutions?</h2>
      <p class="lead text-muted">What sets us apart from other technology consultants</p>
    </div>
    
    <div class="row g-4">
      <div class="col-md-4 reveal">
        <div class="why-card text-center p-4">
          <div>
            <i class="bi bi-people-fill text-primary mb-3" style="font-size: 2.5rem;"></i>
            <h5>Experienced Industry Experts</h5>
            <p class="mb-0">Our team averages 15+ years in technology consulting across diverse industries.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4 reveal">
        <div class="why-card text-center p-4">
          <div>
            <i class="bi bi-person-heart text-primary mb-3" style="font-size: 2.5rem;"></i>
            <h5>Client-Centered Approach</h5>
            <p class="mb-0">We become true partners, invested in your success and aligned with your goals.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4 reveal">
        <div class="why-card text-center p-4">
          <div>
            <i class="bi bi-graph-up-arrow text-primary mb-3" style="font-size: 2.5rem;"></i>
            <h5>Measurable Business Results</h5>
            <p class="mb-0">We focus on delivering tangible ROI and quantifiable improvements to your operations.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= TESTIMONIALS ================= -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="section-header reveal">
      <h2>Client Success Stories</h2>
      <p class="lead text-muted">See what our clients have to say about working with us</p>
    </div>
    
    <div class="testimonial-box reveal">
      <p class="mb-4" id="testimonialText">
        "NexGen Solutions transformed our IT infrastructure from a liability into a competitive advantage. Their team modernized our systems, improved security, and helped us leverage data in ways we never thought possible. Our operational efficiency improved by 40% within the first year."
      </p>
      <div class="d-flex align-items-center justify-content-center">
        <div class="me-3">
          <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
            <span class="text-white fw-bold">SM</span>
          </div>
        </div>
        <div>
          <strong id="testimonialAuthor">Sarah M., Operations Director</strong>
          <p class="text-muted mb-0" id="testimonialCompany">Global Manufacturing Inc.</p>
        </div>
      </div>
      <div class="text-center mt-4">
        <button class="btn btn-sm btn-outline-primary me-2" id="prevTestimonial">← Previous</button>
        <button class="btn btn-sm btn-outline-primary" id="nextTestimonial">Next →</button>
      </div>
    </div>
  </div>
</section>

<!-- ================= CONTACT ================= -->
<section id="contact" class="contact-section">
  <div class="container">
    <div class="section-header text-white reveal">
      <h2 class="text-white">Get in Touch</h2>
      <p class="lead">Ready to transform your technology and move your business forward?</p>
    </div>

    <div class="row">
      <div class="col-lg-8 mx-auto">
        <form id="contactForm" class="contact-form reveal" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <input type="text" id="name" class="form-control" placeholder="Your Name" required name="name">
              <div class="invalid-feedback">Please enter your name.</div>
            </div>
            <div class="col-md-4">
              <input type="email" id="email" class="form-control" placeholder="Email Address"  name="email"required>
              <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>
            <div class="col-md-4">
              <input type="tel" id="phone" class="form-control" name="phone" placeholder="Phone Number">
            </div>
          </div>
          <div class="mb-4">
            <select class="form-control" id="service" required name="service">
              <option value="" selected disabled>Select Service Interested In</option>
              <option value="IT Consulting">IT Consulting</option>
              <option value="Cloud Services">Cloud Services</option>
              <option value="Cybersecurity">Cybersecurity</option>
              <option value="Software Development">Software Development</option>
              <option value="Data Analytics">Data Analytics</option>
              <option value="Digital Transformation">Digital Transformation</option>
              <option value="Managed IT Services">Managed IT Services</option>
              <option value="other">Other</option>
            </select>
            <div class="invalid-feedback">Please select a service.</div>
          </div>
          <div class="mb-4">
            <textarea id="message" class="form-control" rows="4" placeholder="Tell us about your project or requirements..." required name="message"></textarea>
            <div class="invalid-feedback">Please enter your message.</div>
          </div>
          <div class="mb-4 form-check">
            <input type="checkbox" class="form-check-input" id="newsletter" checked>
            <label class="form-check-label" for="newsletter">Subscribe to our newsletter for tech insights and updates</label>
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-warning btn-lg px-5" name="submit">
              <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="display: none;"></span>
              Send Message
            </button>
          </div>
        </form>
        
        <!-- Then in your HTML, add this after the form -->
<?php if (isset($_SESSION['form_success'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo $_SESSION['form_success']; ?>', 'success');
    });
</script>
<?php 
unset($_SESSION['form_success']);
endif; ?>

<?php if (isset($_SESSION['form_error'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo $_SESSION['form_error']; ?>', 'error');
    });
</script>
<?php 
unset($_SESSION['form_error']);
endif; ?>

        <div class="row mt-5 text-center text-white reveal">
          <div class="col-md-4 mb-3">
            <i class="bi bi-geo-alt-fill h3 text-warning"></i>
            <h5 class="mt-2">Visit Our Office</h5>
            <p class="mb-0">123 Tech Street<br>San Francisco, CA 94107</p>
          </div>
          <div class="col-md-4 mb-3">
            <i class="bi bi-telephone-fill h3 text-warning"></i>
            <h5 class="mt-2">Call Us</h5>
            <p class="mb-0">+1 (555) 123-4567<br>Mon-Fri, 9am-6pm PST</p>
          </div>
          <div class="col-md-4 mb-3">
            <i class="bi bi-envelope-fill h3 text-warning"></i>
            <h5 class="mt-2">Email Us</h5>
            <p class="mb-0">info@nexgensolutions.com<br>Responses within 24 hours</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= FOOTER ================= -->
<footer class="footer">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6 text-md-start text-center mb-3 mb-md-0">
        <h3 class="text-white mb-2"><span class="text-primary">Nex</span>Gen Solutions</h3>
        <p class="text-muted mb-0">Transforming businesses through innovative technology since 1999.</p>
      </div>
      <div class="col-md-6 text-md-end text-center">
        <div class="footer-links">
          <a href="#home">Home</a>
          <a href="#about">About</a>
          <a href="#services">Services</a>
          <a href="#cases">Case Studies</a>
          <a href="#contact">Contact</a>
        </div>
      </div>
    </div>
    <hr class="my-4 bg-secondary">
    <div class="row">
      <div class="col-md-6 text-md-start text-center mb-2 mb-md-0">
        <p class="mb-0 text-muted">© 2025 NexGen Solutions. All rights reserved. | <a href="#" class="text-muted text-decoration-none">Privacy Policy</a> | <a href="#" class="text-muted text-decoration-none">Terms of Service</a></p>
      </div>
      <div class="col-md-6 text-md-end text-center">
        <a href="#" class="text-muted me-3"><i class="bi bi-linkedin"></i></a>
        <a href="#" class="text-muted me-3"><i class="bi bi-twitter"></i></a>
        <a href="#" class="text-muted me-3"><i class="bi bi-facebook"></i></a>
        <a href="#" class="text-muted"><i class="bi bi-instagram"></i></a>
      </div>
    </div>
  </div>
</footer>

<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Back to Top Button -->
<a href="#home" class="back-to-top">
  <i class="bi bi-arrow-up"></i>
</a>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script>
  // Initialize when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    
    // 1. SCROLL REVEAL ANIMATION
    const reveals = document.querySelectorAll('.reveal');
    const revealOnScroll = () => {
      reveals.forEach(element => {
        const windowHeight = window.innerHeight;
        const revealTop = element.getBoundingClientRect().top;
        const revealPoint = 100;
        
        if (revealTop < windowHeight - revealPoint) {
          element.classList.add('active');
        }
      });
    };
    window.addEventListener('scroll', revealOnScroll);
    revealOnScroll(); // Initial check
    
    // 2. NAVBAR SCROLL EFFECT
    window.addEventListener('scroll', function() {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });
    
    // 3. ANIMATED COUNTER FOR STATS
    const statNumbers = document.querySelectorAll('.stat-number[data-count]');
    const animateCounter = () => {
      statNumbers.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-count'));
        const current = parseInt(stat.innerText);
        const increment = target / 100;
        
        if (current < target) {
          stat.innerText = Math.ceil(current + increment);
          setTimeout(animateCounter, 20);
        } else {
          stat.innerText = target;
        }
      });
    };
    
    // Trigger counter when stats section is visible
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter();
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    
    const statsSection = document.querySelector('.bg-light.py-5');
    if (statsSection) observer.observe(statsSection);
    
    // 4. ACTIVE NAV LINK ON SCROLL
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    const setActiveNavLink = () => {
      let current = '';
      sections.forEach(section => {
        const sectionTop = section.offsetTop - 100;
        const sectionHeight = section.clientHeight;
        if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) {
          current = section.getAttribute('id');
        }
      });
      
      navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
          link.classList.add('active');
        }
      });
    };
    window.addEventListener('scroll', setActiveNavLink);
    
    // 5. BACK TO TOP BUTTON
    const backToTop = document.querySelector('.back-to-top');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) {
        backToTop.classList.add('show');
      } else {
        backToTop.classList.remove('show');
      }
    });
    
    // 6. TESTIMONIAL SLIDER
    const testimonials = [
      {
        text: "NexGen Solutions transformed our IT infrastructure from a liability into a competitive advantage. Their team modernized our systems, improved security, and helped us leverage data in ways we never thought possible. Our operational efficiency improved by 40% within the first year.",
        author: "Sarah M., Operations Director",
        company: "Global Manufacturing Inc."
      },
      {
        text: "Their cybersecurity overhaul protected us from a major attack that would have cost millions. The team was proactive, knowledgeable, and always available when we needed them. I sleep better at night knowing our systems are secure.",
        author: "James L., Chief Technology Officer",
        company: "Financial Services Corp"
      },
      {
        text: "The cloud migration was seamless and exceeded our expectations. We cut infrastructure costs by 35% while improving performance and scalability. Their ongoing support has been exceptional.",
        author: "Maria G., VP of Technology",
        company: "Retail Chain Inc."
      }
    ];
    
    let currentTestimonial = 0;
    const testimonialText = document.getElementById('testimonialText');
    const testimonialAuthor = document.getElementById('testimonialAuthor');
    const testimonialCompany = document.getElementById('testimonialCompany');
    const prevBtn = document.getElementById('prevTestimonial');
    const nextBtn = document.getElementById('nextTestimonial');
    
    const updateTestimonial = (index) => {
      testimonialText.textContent = testimonials[index].text;
      testimonialAuthor.textContent = testimonials[index].author;
      testimonialCompany.textContent = testimonials[index].company;
    };
    
    if (prevBtn && nextBtn) {
      prevBtn.addEventListener('click', () => {
        currentTestimonial = (currentTestimonial - 1 + testimonials.length) % testimonials.length;
        updateTestimonial(currentTestimonial);
      });
      
      nextBtn.addEventListener('click', () => {
        currentTestimonial = (currentTestimonial + 1) % testimonials.length;
        updateTestimonial(currentTestimonial);
      });
    }
    
    // 7. SERVICE CARD CLICK TO AUTO-FILL FORM
    const serviceCards = document.querySelectorAll('.feature-box, .service-card');
    serviceCards.forEach(card => {
      card.addEventListener('click', function() {
        const service = this.getAttribute('data-service');
        if (service) {
          document.getElementById('service').value = service;
          // Fix: Update the select to show selected value properly
          const select = document.getElementById('service');
          select.style.color = 'white';
          document.getElementById('contact').scrollIntoView({ behavior: 'smooth' });
          showToast(`Selected "${service}" service`, 'info');
        }
      });
    });
    
    // 8. CASE STUDY MODAL FUNCTIONALITY
    const caseCards = document.querySelectorAll('.case-study-card');
    caseCards.forEach(card => {
      card.addEventListener('click', function() {
        const caseType = this.getAttribute('data-case');
        const title = this.querySelector('h5').textContent;
        showToast(`Opening case study: ${title}`, 'info');
        // In a real application, this would open a modal or navigate to a detailed page
      });
    });
    
    // 9. CONTACT FORM VALIDATION AND SUBMISSION
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
      contactForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Reset validation
        const formInputs = contactForm.querySelectorAll('.form-control');
        formInputs.forEach(input => {
          input.classList.remove('is-invalid', 'is-valid');
        });
        
        // Validate form
        let isValid = true;
        const name = document.getElementById('name');
        const email = document.getElementById('email');
        const service = document.getElementById('service');
        const message = document.getElementById('message');
        
        if (!name.value.trim()) {
          name.classList.add('is-invalid');
          isValid = false;
        }
        
        if (!email.value.trim() || !isValidEmail(email.value)) {
          email.classList.add('is-invalid');
          isValid = false;
        }
        
        if (!service.value) {
          service.classList.add('is-invalid');
          isValid = false;
        }
        
        if (!message.value.trim()) {
          message.classList.add('is-invalid');
          isValid = false;
        }
        
        if (!isValid) {
          showToast('Please fill in all required fields correctly.', 'error');
          return;
        }
        
        // Show loading spinner
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const spinner = submitBtn.querySelector('.spinner-border');
        submitBtn.disabled = true;
        spinner.style.display = 'inline-block';
        
        // Simulate API call
        setTimeout(() => {
          // In a real application, you would send data to a server here
          console.log('Form submitted:', {
            name: name.value,
            email: email.value,
            phone: document.getElementById('phone').value,
            service: service.value,
            message: message.value,
            newsletter: document.getElementById('newsletter').checked
          });
          
          // Show success message
          showToast('Thank you! Your quote request has been submitted. We will contact you within 24 hours.', 'success');
          
          // Reset form
          contactForm.reset();
          // Reset select color to placeholder color
          service.style.color = 'rgba(255, 255, 255, 0.6)';
          
          // Reset UI
          submitBtn.disabled = false;
          spinner.style.display = 'none';
          
          // Show confirmation toast
          showToast(`Thank you ${name.value}! We have received your inquiry about ${service.value}.`, 'success');
          
        }, 1500);
      });
    }
    
    // 10. GET QUOTE BUTTONS FUNCTIONALITY
    const getQuoteButtons = [
      document.getElementById('getQuoteBtn'),
      document.getElementById('heroQuoteBtn'),
      document.getElementById('aboutQuoteBtn')
    ];
    
    getQuoteButtons.forEach(button => {
      if (button) {
        button.addEventListener('click', function() {
          document.getElementById('contact').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
          });
          showToast('Scroll to contact form to get your quote', 'info');
        });
      }
    });
    
    // 11. EMAIL VALIDATION HELPER
    function isValidEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }
    
    // 12. TOAST NOTIFICATION SYSTEM
    function showToast(message, type = 'info') {
      const toastContainer = document.querySelector('.toast-container');
      const toastId = 'toast-' + Date.now();
      
      const icons = {
        success: 'bi-check-circle-fill',
        error: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill',
        warning: 'bi-exclamation-triangle-fill'
      };
      
      const colors = {
        success: '#198754',
        error: '#dc3545',
        info: '#0d6efd',
        warning: '#ffc107'
      };
      
      const toast = document.createElement('div');
      toast.className = 'toast align-items-center border-0';
      toast.id = toastId;
      toast.style.borderLeftColor = colors[type] || colors.info;
      
      toast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body p-3">
            <i class="bi ${icons[type] || icons.info} me-2" style="color: ${colors[type] || colors.info}"></i>
            ${message}
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      `;
      
      toastContainer.appendChild(toast);
      const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
      bsToast.show();
      
      // Remove toast from DOM after it's hidden
      toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
      });
    }
    
    // 13. FORM INPUT REAL-TIME VALIDATION
    const formInputs = document.querySelectorAll('#contactForm .form-control');
    formInputs.forEach(input => {
      input.addEventListener('input', function() {
        if (this.value.trim()) {
          this.classList.remove('is-invalid');
          this.classList.add('is-valid');
          // For select, update text color
          if (this.id === 'service') {
            this.style.color = 'white';
          }
        } else {
          this.classList.remove('is-valid');
          // For select, reset color if empty
          if (this.id === 'service') {
            this.style.color = 'rgba(255, 255, 255, 0.6)';
          }
        }
      });
    });
    
    // 14. Fix select dropdown color on change
    const serviceSelect = document.getElementById('service');
    if (serviceSelect) {
      serviceSelect.addEventListener('change', function() {
        if (this.value) {
          this.style.color = 'white';
        } else {
          this.style.color = 'rgba(255, 255, 255, 0.6)';
        }
      });
      
      // Set initial color
      serviceSelect.style.color = 'rgba(255, 255, 255, 0.6)';
    }
    
    // 15. Force dropdown options to have proper styling
    // This is a workaround for some browsers
    document.addEventListener('click', function(e) {
      if (e.target.id === 'service') {
        // Force dropdown to open with proper styling
        setTimeout(() => {
          const options = document.querySelectorAll('#service option');
          options.forEach(option => {
            option.style.backgroundColor = '#212529';
            option.style.color = 'white';
          });
        }, 10);
      }
    });
    
    // 16. INITIALIZE ALL FUNCTIONALITY
    console.log('NexGen Solutions website initialized successfully');
    
    // Force style dropdown options on page load
    setTimeout(() => {
      const options = document.querySelectorAll('#service option');
      options.forEach(option => {
        option.style.backgroundColor = '#212529';
        option.style.color = 'white';
      });
    }, 100);
  });
</script>
</body>
</html>