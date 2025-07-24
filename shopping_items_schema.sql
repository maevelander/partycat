-- Party Cat Database Schema
-- This schema creates all the main application tables for Party Cat

-- Create parties table
CREATE TABLE IF NOT EXISTS parties (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    user_id uuid REFERENCES auth.users(id) ON DELETE CASCADE,
    name text NOT NULL DEFAULT '😸 Party Time Meow!',
    description text DEFAULT 'My awesome party!',
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);

-- Create guests table
CREATE TABLE IF NOT EXISTS guests (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    party_id uuid REFERENCES parties(id) ON DELETE CASCADE,
    name text NOT NULL,
    status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'yes', 'no')),
    count integer NOT NULL DEFAULT 1,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);

-- Create todos table
CREATE TABLE IF NOT EXISTS todos (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    party_id uuid REFERENCES parties(id) ON DELETE CASCADE,
    text text NOT NULL,
    completed boolean NOT NULL DEFAULT false,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);

-- Create shopping_items table
CREATE TABLE IF NOT EXISTS shopping_items (
    id uuid DEFAULT gen_random_uuid() PRIMARY KEY,
    party_id uuid REFERENCES parties(id) ON DELETE CASCADE,
    text text NOT NULL,
    bought boolean NOT NULL DEFAULT false,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now()
);

-- Enable Row Level Security (RLS) on all tables
ALTER TABLE parties ENABLE ROW LEVEL SECURITY;
ALTER TABLE guests ENABLE ROW LEVEL SECURITY;
ALTER TABLE todos ENABLE ROW LEVEL SECURITY;
ALTER TABLE shopping_items ENABLE ROW LEVEL SECURITY;

-- RLS Policies for parties table
CREATE POLICY "Users can view their own parties" ON parties
    FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own parties" ON parties
    FOR INSERT WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own parties" ON parties
    FOR UPDATE USING (auth.uid() = user_id);

CREATE POLICY "Users can delete their own parties" ON parties
    FOR DELETE USING (auth.uid() = user_id);

-- RLS Policies for guests table
CREATE POLICY "Users can view guests for their parties" ON guests
    FOR SELECT USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can insert guests for their parties" ON guests
    FOR INSERT WITH CHECK (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can update guests for their parties" ON guests
    FOR UPDATE USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can delete guests for their parties" ON guests
    FOR DELETE USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

-- RLS Policies for todos table
CREATE POLICY "Users can view todos for their parties" ON todos
    FOR SELECT USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can insert todos for their parties" ON todos
    FOR INSERT WITH CHECK (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can update todos for their parties" ON todos
    FOR UPDATE USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can delete todos for their parties" ON todos
    FOR DELETE USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

-- RLS Policies for shopping_items table
CREATE POLICY "Users can view shopping items for their parties" ON shopping_items
    FOR SELECT USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can insert shopping items for their parties" ON shopping_items
    FOR INSERT WITH CHECK (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can update shopping items for their parties" ON shopping_items
    FOR UPDATE USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

CREATE POLICY "Users can delete shopping items for their parties" ON shopping_items
    FOR DELETE USING (
        party_id IN (
            SELECT id FROM parties WHERE user_id = auth.uid()
        )
    );

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_parties_user_id ON parties(user_id);
CREATE INDEX IF NOT EXISTS idx_guests_party_id ON guests(party_id);
CREATE INDEX IF NOT EXISTS idx_guests_created_at ON guests(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_todos_party_id ON todos(party_id);
CREATE INDEX IF NOT EXISTS idx_todos_created_at ON todos(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_shopping_items_party_id ON shopping_items(party_id);
CREATE INDEX IF NOT EXISTS idx_shopping_items_created_at ON shopping_items(created_at DESC);

-- Create updated_at trigger function
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply updated_at triggers to all tables
CREATE TRIGGER update_parties_updated_at BEFORE UPDATE ON parties
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_guests_updated_at BEFORE UPDATE ON guests
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_todos_updated_at BEFORE UPDATE ON todos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_shopping_items_updated_at BEFORE UPDATE ON shopping_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();