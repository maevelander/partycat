# 🎉 Party Cat

A comprehensive party planning web application with dual-mode functionality: work offline with local storage or sync to the cloud with Supabase. Perfect for organizing parties, tracking RSVPs, managing todos, and creating shopping lists.

![Party Cat Screenshot](purrrfect.jpg)

## ✨ Features

### 🎯 Core Functionality
- **RSVP Tracker** - Manage guest lists with intelligent people count detection
- **Todo Management** - Track party planning tasks with completion status
- **Shopping Lists** - Organize items to buy with check-off functionality
- **Dual Mode Operation** - Works offline (localStorage) or online (Supabase cloud sync)

### 🧠 Smart Features
- **Intelligent Guest Detection** - Automatically estimates party size from guest names
  - "Smith Family" → 4 people
  - "John & Jane" → 2 people  
  - "Bob, Alice, Charlie" → 3 people
- **Responsive Design** - Mobile-first approach with progressive enhancement
- **Real-time Analytics** - Anonymous usage tracking for insights
- **Secure Authentication** - Optional account creation with password reset

### 🔒 Privacy & Security
- **Row Level Security (RLS)** - User data isolation in the database
- **Anonymous Analytics** - No personal data tracking
- **Offline-First** - Works without internet connection
- **Secure Config** - Credentials stored separately from code

## 🚀 Quick Start

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/partycat.git
cd partycat
```

### 2. Set Up Configuration
```bash
# Copy the example config
cp config-example.js config.js

# Edit config.js with your Supabase credentials
# (Optional - app works offline without Supabase)
```

### 3. Run the Application
```bash
# Option 1: Open index.html directly in your browser
open index.html

# Option 2: Use a local server
python -m http.server 8000
# Then visit http://localhost:8000
```

## ⚙️ Configuration

### Supabase Setup (Optional)
If you want cloud sync functionality:

1. Create a [Supabase](https://supabase.com) account
2. Create a new project
3. Run the SQL schema files:
   - `shopping_items_schema.sql`
   - `analytics_events_schema.sql`
4. Update `config.js` with your project credentials:

```javascript
const CONFIG = {
    SUPABASE_URL: 'https://your-project-ref.supabase.co',
    SUPABASE_ANON_KEY: 'your-anon-key-here',
    ANALYTICS_PASSWORD_HASH: 'your-password-hash-here'
};
```

### Analytics Dashboard
Access the admin analytics dashboard at `/analytics.html` (password protected).

## 🏗️ Architecture

### File Structure
```
partycat/
├── index.html              # Main application
├── about.html              # About page
├── legal.html              # Legal/privacy page
├── analytics.html          # Admin dashboard
├── style.css               # Styling
├── config-example.js       # Configuration template
├── config.js               # Your credentials (gitignored)
├── purrrfect.jpg          # Cat mascot image
├── shopping_items_schema.sql
├── analytics_events_schema.sql
└── README.md
```

### Key Components

**Authentication System**
- `showUpgradeModal()` - Prompts guest users to create accounts
- `handleLogin()` / `handleSignup()` - Supabase authentication
- `handleForgotPassword()` - Password reset functionality

**Data Management**
- **Guest Mode**: localStorage for all data
- **Cloud Mode**: Supabase database with RLS policies
- Automatic data loading and synchronization

**Smart Parsing**
- `parseGuestCount()` - Intelligent guest count detection
- Handles families, couples, and group names automatically

## 🎨 Design Philosophy

- **Apple-inspired Design** - Clean, modern interface with smooth animations
- **Mobile-First** - Responsive design that works on all devices  
- **Progressive Enhancement** - Core functionality works without JavaScript
- **Accessibility** - Keyboard navigation and screen reader support

## 🔧 Development

### Local Development
No build process required! This is a vanilla HTML/CSS/JavaScript application.

```bash
# Serve locally
python -m http.server 8000
# or
npx http-server
```

### Database Schema
The application uses these Supabase tables:
- `parties` - Party information
- `guests` - Guest list with RSVP status  
- `todos` - Party planning tasks
- `shopping_items` - Shopping list items
- `analytics_events` - Anonymous usage tracking

All tables use Row Level Security (RLS) for data isolation.

## 📱 Usage

### Guest Mode (Offline)
1. Open the app in your browser
2. Click "Continue as Guest"
3. Start adding guests, todos, and shopping items
4. Data is saved locally in your browser

### Cloud Mode (Online)
1. Click "Create Account" or "Login"
2. Enter your email and password
3. Your data syncs across devices
4. Upgrade from guest mode preserves existing data

### Smart Guest Entry
- Type "Smith Family" → automatically counts as 4 people
- Type "John & Jane" → automatically counts as 2 people
- Type "Bob, Alice, Charlie" → automatically counts as 3 people

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🐱 About Party Cat

Party Cat makes party planning purr-fect! Whether you're organizing a birthday bash, wedding reception, or casual get-together, Party Cat helps you stay organized with style.

Built with ❤️ using vanilla JavaScript, modern CSS, and Supabase.

---

**Questions or suggestions?** Open an issue or reach out!