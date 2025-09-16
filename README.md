# 🚀 BIG PLAN TRACKER

**Modern Project Management System**  
*GitHub + Render + MongoDB Stack*

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy)

## 🎯 Overview

BIG PLAN TRACKER is a professional project management system built with modern technologies:

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 8.0+ with MongoDB
- **Database:** MongoDB Atlas (Cloud)
- **Hosting:** Render (Auto-deployments from GitHub)
- **Version Control:** GitHub

## ✨ Features

- 👥 **User Management** - Role-based access control
- 🏢 **Company Management** - Multi-tenant support
- 📋 **Project Tracking** - Complete project lifecycle
- ✅ **Task Management** - Assign, track, and complete tasks
- 📊 **Activity Logging** - Detailed audit trail
- 🔐 **Secure Authentication** - JWT-based sessions
- 📱 **Responsive Design** - Works on all devices
- 🚀 **Modern UI** - Glass morphism design

## 🚀 Quick Deploy

### Option 1: One-Click Deploy
[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/yourusername/big-plan-tracker)

### Option 2: Manual Setup
1. **Fork this repository**
2. **Set up MongoDB Atlas** (free tier)
3. **Deploy to Render** (free tier)
4. **Configure environment variables**

📖 **[Complete Setup Guide](DEPLOYMENT_GUIDE.html)** - Step-by-step instructions for beginners!

## 🛠️ Local Development

### Prerequisites
- PHP 8.0+
- Composer
- MongoDB Atlas account

### Setup
```bash
# Clone repository
git clone https://github.com/yourusername/big-plan-tracker.git
cd big-plan-tracker

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Edit .env with your MongoDB credentials
nano .env

# Start development server
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`

## 🔧 Configuration

### Environment Variables
```env
MONGODB_URI=mongodb+srv://user:pass@cluster.mongodb.net/db?retryWrites=true&w=majority
MONGODB_DATABASE=bigplantracker
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=your-super-secret-key
```

### MongoDB Collections
- `users` - User accounts and profiles
- `companies` - Company/organization data
- `projects` - Project information
- `tasks` - Task details and assignments
- `activities` - Activity logs and history

## 📁 Project Structure

```
big-plan-tracker/
├── 📄 composer.json          # PHP dependencies
├── 📄 package.json           # Node.js metadata
├── 📄 render.yaml            # Render deployment config
├── 📄 .env.example           # Environment template
├── 📁 public/                # Web root
│   ├── 📄 index.php          # Main entry point
│   ├── 📁 views/             # HTML templates
│   └── 📁 assets/            # CSS, JS, images
└── 📁 src/                   # Application code
    ├── 📁 config/            # Configuration classes
    └── 📁 api/               # API endpoints
```

## 🔗 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/health` | GET | System health check |
| `/api/login` | POST | User authentication |
| `/api/logout` | POST | User logout |
| `/api/users` | GET/POST | User management |
| `/api/projects` | GET/POST | Project management |
| `/api/tasks` | GET/POST | Task management |

## 🎨 Demo Credentials

**Administrator:**
- Username: `admin`
- Password: `admin123`

**Regular User:**
- Username: `user`
- Password: `user123`

## 🔒 Security Features

- ✅ JWT-based authentication
- ✅ Password hashing (bcrypt)
- ✅ CORS protection
- ✅ Input validation
- ✅ SQL injection prevention (MongoDB)
- ✅ XSS protection
- ✅ Environment-based configuration

## 📊 Monitoring

- **Health Check:** `/api/health`
- **Render Logs:** Available in dashboard
- **MongoDB Metrics:** Atlas monitoring
- **Error Tracking:** PHP error logs

## 🚀 Deployment

### Render (Recommended)
1. Connect GitHub repository
2. Set environment variables
3. Deploy automatically

### Other Platforms
- **Heroku:** Compatible with buildpacks
- **DigitalOcean App Platform:** Direct deployment
- **AWS Elastic Beanstalk:** PHP platform
- **Google Cloud Run:** Container deployment

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- 📖 **[Deployment Guide](DEPLOYMENT_GUIDE.html)** - Complete setup instructions
- 🐛 **[Issues](https://github.com/yourusername/big-plan-tracker/issues)** - Bug reports and feature requests
- 💬 **[Discussions](https://github.com/yourusername/big-plan-tracker/discussions)** - Community support

## 🎉 Why This Stack?

### vs. Traditional LAMP Stack
- ✅ **MongoDB:** More flexible than MySQL
- ✅ **Render:** Better than shared hosting
- ✅ **GitHub:** Professional version control
- ✅ **Auto-deployments:** No manual uploads

### vs. InfinityFree
- ✅ **No weird limitations**
- ✅ **Professional infrastructure**
- ✅ **Automatic backups**
- ✅ **SSL certificates included**
- ✅ **Better performance**

---

**Made with ❤️ for modern project management**

*Deploy once, scale forever! 🚀*