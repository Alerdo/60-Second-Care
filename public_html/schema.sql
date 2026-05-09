CREATE TABLE health_check_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anonymous_session_id VARCHAR(64) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  age_range VARCHAR(20),
  sex VARCHAR(30),
  selected_body_area VARCHAR(50),
  precise_location VARCHAR(100),
  duration VARCHAR(50),
  severity VARCHAR(20),
  worsening_status VARCHAR(20),
  warning_symptoms TEXT,
  red_flag_triggered TINYINT(1) DEFAULT 0,
  urgency_level VARCHAR(20),
  confidence VARCHAR(20),
  model_id VARCHAR(50),
  app_version VARCHAR(20),
  consent_to_store TINYINT(1) DEFAULT 0
);

CREATE TABLE health_check_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anonymous_session_id VARCHAR(64),
  health_check_session_id INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  helpfulness VARCHAR(20),
  missing_reason VARCHAR(50),
  feedback_text TEXT
);

CREATE TABLE consent_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anonymous_session_id VARCHAR(64),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  consent_type VARCHAR(50),
  consent_value TINYINT(1),
  consent_version VARCHAR(20)
);
