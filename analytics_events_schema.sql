-- Party Cat Analytics Schema
-- This schema creates the analytics tracking table for Party Cat

-- Create analytics_events table for anonymous usage tracking
CREATE TABLE IF NOT EXISTS analytics_events (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    event_name text NOT NULL,
    session_id text NOT NULL,
    properties jsonb DEFAULT '{}',
    user_agent text,
    url text,
    created_at timestamp with time zone DEFAULT now()
);

-- Enable Row Level Security (RLS)
ALTER TABLE analytics_events ENABLE ROW LEVEL SECURITY;

-- RLS Policy: Allow anonymous users to insert analytics events
-- This enables tracking without requiring authentication
CREATE POLICY "Allow anonymous inserts for analytics" ON analytics_events
    FOR INSERT 
    WITH CHECK (true);

-- RLS Policy: Only allow admin to read analytics data
-- IMPORTANT: Replace 'your-admin-email@example.com' with your actual admin email
CREATE POLICY "Admin can read analytics data" ON analytics_events
    FOR SELECT USING (
        auth.jwt() ->> 'email' = 'your-admin-email@example.com'
        OR 
        auth.uid() IS NULL -- Allow anonymous access for the dashboard with password
    );

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_analytics_events_event_name ON analytics_events(event_name);
CREATE INDEX IF NOT EXISTS idx_analytics_events_session_id ON analytics_events(session_id);
CREATE INDEX IF NOT EXISTS idx_analytics_events_created_at ON analytics_events(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_analytics_events_properties ON analytics_events USING gin(properties);

-- Create a partial index for specific event names that are frequently queried
CREATE INDEX IF NOT EXISTS idx_analytics_events_upgrade_events ON analytics_events(event_name, created_at DESC)
    WHERE event_name IN ('upgrade_modal_shown', 'upgrade_started');

-- Create a composite index for session-based queries
CREATE INDEX IF NOT EXISTS idx_analytics_events_session_date ON analytics_events(session_id, created_at);

-- Grant necessary permissions for anonymous access
-- This allows the anon role to insert analytics events
GRANT INSERT ON analytics_events TO anon;
GRANT USAGE ON SEQUENCE analytics_events_id_seq TO anon;

-- Comments for documentation
COMMENT ON TABLE analytics_events IS 'Stores anonymous usage analytics for the Party Cat application';
COMMENT ON COLUMN analytics_events.event_name IS 'The name of the tracked event (e.g., guest_added, todo_completed)';
COMMENT ON COLUMN analytics_events.session_id IS 'Unique session identifier for grouping user actions';
COMMENT ON COLUMN analytics_events.properties IS 'JSON object containing event-specific properties and metadata';
COMMENT ON COLUMN analytics_events.user_agent IS 'Browser user agent string for device/browser analytics';
COMMENT ON COLUMN analytics_events.url IS 'The URL where the event occurred';

-- Create a view for easier analytics queries (optional)
CREATE OR REPLACE VIEW analytics_summary AS
SELECT 
    event_name,
    COUNT(*) as event_count,
    COUNT(DISTINCT session_id) as unique_sessions,
    DATE(created_at) as event_date
FROM analytics_events 
GROUP BY event_name, DATE(created_at)
ORDER BY event_date DESC, event_count DESC;

-- Grant access to the view
GRANT SELECT ON analytics_summary TO authenticated;

-- Example usage and common queries:
/*

-- Get event counts by name
SELECT event_name, COUNT(*) as count
FROM analytics_events 
WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY event_name 
ORDER BY count DESC;

-- Get daily active sessions
SELECT DATE(created_at) as date, COUNT(DISTINCT session_id) as active_sessions
FROM analytics_events 
WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
GROUP BY DATE(created_at) 
ORDER BY date DESC;

-- Get browser usage from user agents
SELECT 
    CASE 
        WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome'
        WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
        WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
        WHEN user_agent LIKE '%Edg%' THEN 'Edge'
        ELSE 'Other'
    END as browser,
    COUNT(*) as usage_count
FROM analytics_events 
WHERE user_agent IS NOT NULL
    AND created_at >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY browser 
ORDER BY usage_count DESC;

-- Get conversion funnel (upgrade flow)
SELECT 
    event_name,
    COUNT(*) as count,
    COUNT(DISTINCT session_id) as unique_sessions
FROM analytics_events 
WHERE event_name IN ('upgrade_modal_shown', 'upgrade_started', 'signup_completed')
    AND created_at >= CURRENT_DATE - INTERVAL '7 days'
GROUP BY event_name;

*/