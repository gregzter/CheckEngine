-- ====================================
-- CHECKENGINE - Database Init
-- PostgreSQL 17
-- ====================================

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- For text search

-- Create custom types
CREATE TYPE trip_type AS ENUM ('city', 'highway', 'mixed', 'unknown');

-- ====================================
-- TABLES
-- ====================================

-- Vehicles table
CREATE TABLE vehicles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100) DEFAULT 'Prius+',
    year INT,
    mileage_km INT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Trips table
CREATE TABLE trips (
    id SERIAL PRIMARY KEY,
    vehicle_id INT REFERENCES vehicles(id) ON DELETE CASCADE,
    
    -- File info
    filename VARCHAR(255) NOT NULL,
    file_hash VARCHAR(64) UNIQUE, -- To avoid duplicate uploads
    uploaded_at TIMESTAMP DEFAULT NOW(),
    
    -- Trip metadata
    trip_date TIMESTAMP NOT NULL,
    duration_seconds INT,
    distance_km FLOAT,
    trip_type trip_type DEFAULT 'unknown',
    
    -- Calculated metrics
    catalyst_efficiency FLOAT,
    health_score INT CHECK (health_score BETWEEN 0 AND 100),
    
    -- O2 Sensors
    o2_amont_avg FLOAT,
    o2_amont_std FLOAT,
    o2_aval_avg FLOAT,
    o2_aval_std FLOAT,
    
    -- Fuel Trims
    stft_avg FLOAT,
    stft_min FLOAT,
    stft_max FLOAT,
    ltft_avg FLOAT,
    ltft_min FLOAT,
    ltft_max FLOAT,
    
    -- Temperatures
    cat_temp_avg FLOAT,
    cat_temp_max FLOAT,
    coolant_temp_avg FLOAT,
    
    -- Engine stats
    rpm_avg FLOAT,
    engine_load_avg FLOAT,
    maf_avg FLOAT,
    
    -- Analysis metadata
    analyzable_samples INT,
    total_samples INT,
    
    -- User notes
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Trip raw data (time series)
CREATE TABLE trip_data (
    id BIGSERIAL PRIMARY KEY,
    trip_id INT REFERENCES trips(id) ON DELETE CASCADE,
    
    -- Timestamps
    timestamp TIMESTAMP NOT NULL,
    time_seconds FLOAT,
    
    -- Position (optional)
    longitude FLOAT,
    latitude FLOAT,
    gps_speed_ms FLOAT,
    
    -- O2 Sensors
    o2_b1s1_voltage FLOAT,
    o2_b1s2_voltage FLOAT,
    o2_b1s1_wide_range FLOAT,
    o2_b1s1_lambda FLOAT,
    
    -- Fuel System
    stft FLOAT,
    ltft FLOAT,
    afr_measured FLOAT,
    afr_commanded FLOAT,
    
    -- Temperatures
    cat_temp_1 FLOAT,
    cat_temp_2 FLOAT,
    coolant_temp FLOAT,
    intake_temp FLOAT,
    
    -- Engine
    rpm INT,
    engine_load FLOAT,
    maf FLOAT,
    speed_kmh FLOAT,
    
    -- Prius specific
    prius_af_lambda FLOAT,
    prius_afs_voltage FLOAT,
    prius_engine_speed FLOAT,
    
    -- Analysis flags
    engine_running BOOLEAN DEFAULT FALSE,
    engine_warm BOOLEAN DEFAULT FALSE,
    analyzable BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT NOW()
);

-- Aggregated metrics for fast queries
CREATE TABLE trip_metrics (
    id SERIAL PRIMARY KEY,
    trip_id INT REFERENCES trips(id) ON DELETE CASCADE,
    metric_name VARCHAR(100) NOT NULL,
    metric_value FLOAT NOT NULL,
    metric_unit VARCHAR(20),
    metric_category VARCHAR(50), -- 'catalyst', 'fuel_trim', 'temperature', etc.
    
    created_at TIMESTAMP DEFAULT NOW()
);

-- Analysis history (for tracking changes over time)
CREATE TABLE analysis_history (
    id SERIAL PRIMARY KEY,
    trip_id INT REFERENCES trips(id) ON DELETE CASCADE,
    
    analysis_version VARCHAR(20),
    catalyst_efficiency FLOAT,
    health_score INT,
    
    -- Comparison with previous trip
    efficiency_delta FLOAT,
    health_score_delta INT,
    
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ====================================
-- INDEXES for performance
-- ====================================

-- Trips
CREATE INDEX idx_trips_vehicle_id ON trips(vehicle_id);
CREATE INDEX idx_trips_trip_date ON trips(trip_date DESC);
CREATE INDEX idx_trips_uploaded_at ON trips(uploaded_at DESC);
CREATE INDEX idx_trips_catalyst_efficiency ON trips(catalyst_efficiency);
CREATE INDEX idx_trips_health_score ON trips(health_score);

-- Trip Data
CREATE INDEX idx_trip_data_trip_id ON trip_data(trip_id);
CREATE INDEX idx_trip_data_timestamp ON trip_data(timestamp);
CREATE INDEX idx_trip_data_analyzable ON trip_data(analyzable) WHERE analyzable = TRUE;

-- Metrics
CREATE INDEX idx_trip_metrics_trip_id ON trip_metrics(trip_id);
CREATE INDEX idx_trip_metrics_name ON trip_metrics(metric_name);
CREATE INDEX idx_trip_metrics_category ON trip_metrics(metric_category);

-- ====================================
-- FUNCTIONS
-- ====================================

-- Update timestamp on row update
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers for updated_at
CREATE TRIGGER update_vehicles_updated_at BEFORE UPDATE ON vehicles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_trips_updated_at BEFORE UPDATE ON trips
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ====================================
-- VIEWS (for easier queries)
-- ====================================

-- Latest trips with vehicle info
CREATE VIEW v_latest_trips AS
SELECT 
    t.*,
    v.name as vehicle_name,
    v.model as vehicle_model,
    v.year as vehicle_year,
    v.mileage_km as vehicle_mileage
FROM trips t
LEFT JOIN vehicles v ON t.vehicle_id = v.id
ORDER BY t.trip_date DESC;

-- Trip statistics aggregated
CREATE VIEW v_trip_statistics AS
SELECT 
    vehicle_id,
    COUNT(*) as total_trips,
    AVG(catalyst_efficiency) as avg_efficiency,
    AVG(health_score) as avg_health_score,
    MIN(catalyst_efficiency) as min_efficiency,
    MAX(catalyst_efficiency) as max_efficiency,
    AVG(stft_avg) as avg_stft,
    AVG(ltft_avg) as avg_ltft
FROM trips
GROUP BY vehicle_id;

-- ====================================
-- SAMPLE DATA
-- ====================================

-- Insert default vehicle
INSERT INTO vehicles (name, model, year, mileage_km) 
VALUES ('Ma Prius+', 'Prius+ (Prius V)', 2012, 290000);

-- Success message
DO $$
BEGIN
    RAISE NOTICE '✅ Database initialized successfully!';
    RAISE NOTICE 'PostgreSQL version: %', version();
END $$;
