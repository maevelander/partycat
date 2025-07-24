# 🎉 Party Cat

A simple party planning web app - running live at https://partycat.app

![Party Cat Screenshot](screenshot.png)

## ✨ Features
- **RSVP Tracker** - Manage guest lists with intelligent people count detection
- **Todo Management** - Track party planning tasks with completion status
- **Shopping Lists** - Organise items to buy with check-off functionality
- **Dual Mode Operation** - Works offline (localStorage) or online (Supabase cloud sync)
- **Intelligent Guest Detection** - Automatically counts guests
  - "Smith Family" → 4 people
  - "John & Jane" → 2 people
  - "Kate and Steve" → 2 people  
  - "Bob, Alice, Charlie" → 3 people

## 🚀 Development

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
```

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

## 🎨 Design Approach

- **Apple-inspired Design** - Clean, modern interface with smooth animations
- **Mobile-First** - Responsive design that works on all devices  
- **Progressive Enhancement** - Core functionality works without JavaScript
- **Accessibility** - Keyboard navigation and screen reader support

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open source and available under the MIT License.