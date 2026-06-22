# NetEase Account Checker

A complete web-based NetEase account checker with user authentication, API key management, and admin panel.

## Default Login
- Username: admin
- Password: admin123

## Features
- User registration and login
- Upload account lists (.txt files)
- Generate and manage API keys
- Admin panel with user and proxy management
- API endpoint for automated checking

## Deployment on Railway
1. Push this repo to GitHub
2. Connect to Railway
3. Add MySQL database
4. Deploy!

## API Usage
```bash
curl -X POST https://your-app.railway.app/api.php?action=check \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"pass123"}'