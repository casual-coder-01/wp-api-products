WP API Products is a custom WordPress plugin that securely connects a WordPress website with an external Node.js / Express API using JWT authentication.

It allows WordPress to act as a frontend consumer of protected backend services — a very common real-world requirement for modern businesses using headless or microservice architectures.

This project demonstrates how to:

Authenticate WordPress against a secure API

Fetch protected data using JWT

Manage API credentials safely from the WordPress admin panel

Display dynamic data on the frontend using shortcodes




Why This Project Matters 

Many businesses today already have:

A custom backend (Node.js, Laravel, Django, etc.)

A need to show backend data inside WordPress

Security requirements (JWT, OAuth, API tokens)

This plugin shows a production-ready approach to solving that problem — cleanly, securely, and extensibly.

✅ Perfect for SaaS dashboards, product listings, internal tools, headless WordPress setups, and API-driven websites.




Key Features


JWT Authentication

Secure login to external Node.js API

Token generation and reuse

Admin Settings Panel

Configure API URL, username, and password from WordPress dashboard

Protected API Requests

Fetch data only from authenticated endpoints

Shortcode Support

Display API data anywhere in WordPress using a shortcode

Clean & Readable Code

Well-commented, beginner-friendly, and easy to extend

No Hardcoded Secrets

All sensitive data managed via WordPress options



How It Works 


Admin enters API credentials in WordPress

WordPress requests a JWT token from Node.js /login

Token is stored securely in WordPress options

Token is attached to protected /products API requests

Products are displayed on frontend using a shortcode


Designed for Real Projects

This plugin is intentionally built to be:

Easy to customize

Easy to debug

Easy to extend (pagination, caching, UI styling, error handling)

Future enhancements can include:

Token expiration handling

Caching with transients

Elementor widget

Custom product layouts

Role-based access control




About the Developer

Built by Abhinav Thakur, focusing on:

WordPress development

API integrations

Node.js backends

Practical, real-world solutions (not demo-only code)