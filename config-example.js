// Configuration template for Party Cat
// Copy this file to config.js and fill in your actual values

const CONFIG = {
    // Supabase Configuration
    // Get these from your Supabase project dashboard
    SUPABASE_URL: 'https://your-project-ref.supabase.co',
    SUPABASE_ANON_KEY: 'your-supabase-anon-key-here',
    
    // Analytics Configuration
    // Generate a password hash using the hashPassword function in analytics.html
    ANALYTICS_PASSWORD_HASH: 'your-password-hash-here'
};

// Make config available globally
window.CONFIG = CONFIG;