<?php

class DatabaseMigration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function migrate(): array
    {
        $results = [];
        $queries = $this->getMigrations();

        foreach ($queries as $name => $sql) {
            try {
                $this->pdo->exec($sql);
                $results[] = "✓ {$name}";
            } catch (PDOException $e) {
                $results[] = "✗ {$name}: " . $e->getMessage();
            }
        }

        return $results;
    }

    private function getMigrations(): array
    {
        return [
            'create_users_table' => "
                CREATE TABLE IF NOT EXISTS users (
                    id BIGSERIAL PRIMARY KEY,
                    username VARCHAR(64) NOT NULL UNIQUE,
                    email VARCHAR(128) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    real_name VARCHAR(64),
                    avatar_url VARCHAR(255),
                    role VARCHAR(32) NOT NULL DEFAULT 'member',
                    status VARCHAR(32) NOT NULL DEFAULT 'active',
                    last_login_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'create_families_table' => "
                CREATE TABLE IF NOT EXISTS families (
                    id BIGSERIAL PRIMARY KEY,
                    name VARCHAR(128) NOT NULL,
                    description TEXT,
                    owner_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    address VARCHAR(255),
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'create_family_members_table' => "
                CREATE TABLE IF NOT EXISTS family_members (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NOT NULL REFERENCES families(id) ON DELETE CASCADE,
                    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    role VARCHAR(32) NOT NULL DEFAULT 'member',
                    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(family_id, user_id)
                );
            ",
            'create_rooms_table' => "
                CREATE TABLE IF NOT EXISTS rooms (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NOT NULL REFERENCES families(id) ON DELETE CASCADE,
                    name VARCHAR(64) NOT NULL,
                    type VARCHAR(32) NOT NULL DEFAULT 'living_room',
                    icon VARCHAR(32) DEFAULT 'room',
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'create_device_types_table' => "
                CREATE TABLE IF NOT EXISTS device_types (
                    id SERIAL PRIMARY KEY,
                    code VARCHAR(64) NOT NULL UNIQUE,
                    name VARCHAR(128) NOT NULL,
                    category VARCHAR(32) NOT NULL,
                    description TEXT,
                    capabilities JSONB NOT NULL DEFAULT '[]',
                    icon VARCHAR(32) DEFAULT 'device',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'create_devices_table' => "
                CREATE TABLE IF NOT EXISTS devices (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NOT NULL REFERENCES families(id) ON DELETE CASCADE,
                    room_id BIGINT NULL REFERENCES rooms(id) ON DELETE SET NULL,
                    type_id INTEGER NOT NULL REFERENCES device_types(id),
                    name VARCHAR(128) NOT NULL,
                    matter_node_id BIGINT NULL,
                    matter_endpoint INTEGER NULL,
                    matter_device_type INTEGER NULL,
                    matter_vendor_id INTEGER NULL,
                    matter_product_id INTEGER NULL,
                    matter_unique_id VARCHAR(128) NULL UNIQUE,
                    status VARCHAR(32) NOT NULL DEFAULT 'offline',
                    is_online BOOLEAN NOT NULL DEFAULT false,
                    state JSONB NOT NULL DEFAULT '{}',
                    capabilities JSONB NOT NULL DEFAULT '{}',
                    config JSONB NOT NULL DEFAULT '{}',
                    last_seen_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_devices_family ON devices(family_id);
                CREATE INDEX IF NOT EXISTS idx_devices_room ON devices(room_id);
                CREATE INDEX IF NOT EXISTS idx_devices_status ON devices(status);
                CREATE INDEX IF NOT EXISTS idx_devices_matter_uid ON devices(matter_unique_id);
            ",
            'create_device_states_history_table' => "
                CREATE TABLE IF NOT EXISTS device_states_history (
                    id BIGSERIAL PRIMARY KEY,
                    device_id BIGINT NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
                    state JSONB NOT NULL DEFAULT '{}',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_state_history_device ON device_states_history(device_id);
                CREATE INDEX IF NOT EXISTS idx_state_history_created ON device_states_history(created_at);
            ",
            'create_scenes_table' => "
                CREATE TABLE IF NOT EXISTS scenes (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NOT NULL REFERENCES families(id) ON DELETE CASCADE,
                    name VARCHAR(128) NOT NULL,
                    icon VARCHAR(32) DEFAULT 'scene',
                    color VARCHAR(16) DEFAULT '#4A90D9',
                    description TEXT,
                    is_favorite BOOLEAN NOT NULL DEFAULT false,
                    sort_order INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
            ",
            'create_scene_actions_table' => "
                CREATE TABLE IF NOT EXISTS scene_actions (
                    id BIGSERIAL PRIMARY KEY,
                    scene_id BIGINT NOT NULL REFERENCES scenes(id) ON DELETE CASCADE,
                    device_id BIGINT NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
                    action_type VARCHAR(64) NOT NULL,
                    action_params JSONB NOT NULL DEFAULT '{}',
                    delay_ms INTEGER NOT NULL DEFAULT 0,
                    sort_order INTEGER NOT NULL DEFAULT 0
                );
                CREATE INDEX IF NOT EXISTS idx_scene_actions_scene ON scene_actions(scene_id);
            ",
            'create_automation_rules_table' => "
                CREATE TABLE IF NOT EXISTS automation_rules (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NOT NULL REFERENCES families(id) ON DELETE CASCADE,
                    name VARCHAR(128) NOT NULL,
                    description TEXT,
                    is_enabled BOOLEAN NOT NULL DEFAULT true,
                    trigger_type VARCHAR(32) NOT NULL,
                    trigger_config JSONB NOT NULL DEFAULT '{}',
                    conditions JSONB NOT NULL DEFAULT '[]',
                    actions JSONB NOT NULL DEFAULT '[]',
                    last_triggered_at TIMESTAMP NULL,
                    trigger_count INTEGER NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_automation_family ON automation_rules(family_id);
                CREATE INDEX IF NOT EXISTS idx_automation_enabled ON automation_rules(is_enabled);
            ",
            'create_logs_table' => "
                CREATE TABLE IF NOT EXISTS logs (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NULL REFERENCES families(id) ON DELETE SET NULL,
                    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
                    device_id BIGINT NULL REFERENCES devices(id) ON DELETE SET NULL,
                    level VARCHAR(16) NOT NULL DEFAULT 'info',
                    category VARCHAR(64) NOT NULL DEFAULT 'system',
                    message TEXT NOT NULL,
                    context JSONB NOT NULL DEFAULT '{}',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_logs_family ON logs(family_id);
                CREATE INDEX IF NOT EXISTS idx_logs_device ON logs(device_id);
                CREATE INDEX IF NOT EXISTS idx_logs_level ON logs(level);
                CREATE INDEX IF NOT EXISTS idx_logs_created ON logs(created_at);
            ",
            'create_alerts_table' => "
                CREATE TABLE IF NOT EXISTS alerts (
                    id BIGSERIAL PRIMARY KEY,
                    family_id BIGINT NOT NULL REFERENCES families(id) ON DELETE CASCADE,
                    device_id BIGINT NULL REFERENCES devices(id) ON DELETE SET NULL,
                    type VARCHAR(32) NOT NULL,
                    severity VARCHAR(16) NOT NULL DEFAULT 'warning',
                    title VARCHAR(255) NOT NULL,
                    message TEXT,
                    is_read BOOLEAN NOT NULL DEFAULT false,
                    is_resolved BOOLEAN NOT NULL DEFAULT false,
                    resolved_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_alerts_family ON alerts(family_id);
                CREATE INDEX IF NOT EXISTS idx_alerts_read ON alerts(is_read);
            ",
            'insert_default_device_types' => "
                INSERT INTO device_types (code, name, category, capabilities, icon) VALUES
                    ('light', '智能灯', 'lighting', '[\"onoff\",\"brightness\",\"colortemp\",\"color\"]', 'light'),
                    ('switch', '智能开关', 'switch', '[\"onoff\"]', 'switch'),
                    ('outlet', '智能插座', 'switch', '[\"onoff\",\"power\"]', 'outlet'),
                    ('thermostat', '温控器', 'climate', '[\"temperature\",\"humidity\",\"mode\",\"setpoint\"]', 'thermostat'),
                    ('sensor_motion', '人体传感器', 'sensor', '[\"occupancy\",\"battery\"]', 'sensor'),
                    ('sensor_door', '门窗传感器', 'sensor', '[\"contact\",\"battery\"]', 'sensor'),
                    ('sensor_temp', '温湿度传感器', 'sensor', '[\"temperature\",\"humidity\",\"battery\"]', 'sensor'),
                    ('camera', '智能摄像头', 'camera', '[\"stream\",\"motion\",\"nightvision\"]', 'camera'),
                    ('lock', '智能门锁', 'security', '[\"lockstate\",\"battery\"]', 'lock'),
                    ('fan', '智能风扇', 'climate', '[\"onoff\",\"speed\",\"mode\"]', 'fan'),
                    ('curtain', '智能窗帘', 'shade', '[\"position\",\"direction\"]', 'curtain'),
                    ('speaker', '智能音箱', 'media', '[\"volume\",\"playback\"]', 'speaker')
                ON CONFLICT (code) DO NOTHING;
            ",
            'create_user_sessions_table' => "
                CREATE TABLE IF NOT EXISTS user_sessions (
                    id BIGSERIAL PRIMARY KEY,
                    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    token VARCHAR(255) NOT NULL UNIQUE,
                    ip_address VARCHAR(64),
                    user_agent VARCHAR(255),
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_sessions_token ON user_sessions(token);
                CREATE INDEX IF NOT EXISTS idx_sessions_user ON user_sessions(user_id);
            ",
        ];
    }
}
