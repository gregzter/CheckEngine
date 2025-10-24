# API Documentation - CheckEngine CSV/ZIP Upload

## Endpoints

### POST /api/csv-upload

Upload and process CSV or ZIP files containing OBD2 diagnostic data.

#### Request

**Content-Type:** `multipart/form-data`

**Parameters:**
- `csv_file` (required): CSV or ZIP file to upload
  - Max size: 50 MB
  - Formats: `.csv`, `.zip`
  - CSV must contain timestamp column (Device Time or GPS Time)
- `vehicle_id` (optional): Vehicle ID to associate with the trip

#### Response

**Success (201 Created):**

For CSV file:
```json
{
    "success": true,
    "status": "completed",
    "message": "CSV file processed successfully",
    "data": {
        "trip_id": 18,
        "filename": "trackLog-2025-oct.-23_12-00-00.csv",
        "original_name": "trackLog-2025-oct.-23_12-00-00.csv",
        "size": 10698519,
        "columns": 63,
        "data_points": 19706,
        "duration": 1971,
        "status": "analyzed"
    }
}
```

For ZIP file:
```json
{
    "success": true,
    "status": "completed",
    "message": "Processed 1 CSV file(s) from ZIP",
    "data": {
        "original_name": "zipExport-2025-10-23_14-06-10.zip",
        "total_files": 1,
        "successful": 1,
        "failed": 0,
        "trips": [
            {
                "filename": "trackLog-2025-oct.-23_12-00-00.csv",
                "trip_id": 19,
                "status": "analyzed",
                "data_points": 19706,
                "duration": 1971,
                "parse_time": 26.03
            }
        ],
        "errors": []
    },
    "performance": {
        "total_time": 26.07,
        "memory_mb": 16
    }
}
```

**Error (400 Bad Request):**
```json
{
    "success": false,
    "error": "Validation failed",
    "details": ["File size exceeds maximum allowed size"]
}
```

**Error (500 Internal Server Error):**
```json
{
    "success": false,
    "error": "Processing failed",
    "message": "Error details..."
}
```

---

### GET /api/trip-status/{id}

Get trip details and diagnostic results.

#### Request

**URL Parameters:**
- `id`: Trip ID

#### Response

**Success (200 OK):**
```json
{
    "success": true,
    "data": {
        "id": 19,
        "status": "analyzed",
        "filename": "trackLog-2025-oct.-23_12-00-00.csv",
        "session_date": "2025-10-23 12:00:11",
        "duration": 1971,
        "data_points": 19706,
        "analysis_results": {
            "sample_count": 19706,
            "catalyst_efficiency": {
                "status": "insufficient_data",
                "score": null,
                "message": "Not enough O2 sensor data for catalyst analysis"
            },
            "fuel_trim": {
                "status": "excellent",
                "score": 100,
                "short_term_avg": 0.03,
                "long_term_avg": -0.19,
                "total_trim": 0.23,
                "stft_stddev": 0.03,
                "messages": []
            },
            "o2_sensors": {
                "status": "insufficient_data",
                "score": null
            },
            "engine_health": {
                "score": 100,
                "max_rpm": 4623.25,
                "avg_load": 48.4,
                "max_temp": 89,
                "messages": []
            }
        },
        "catalyst_efficiency": null,
        "avg_fuel_trim_st": "0.03",
        "avg_fuel_trim_lt": "-0.19"
    }
}
```

**Error (404 Not Found):**
```json
{
    "success": false,
    "error": "Trip not found"
}
```

---

## Features

### CSV File Processing
- ✅ Streaming parser (handles large files efficiently)
- ✅ Column mapping with 247 variants recognized
- ✅ Timestamp extraction (Device Time, GPS Time)
- ✅ Bulk insert optimization (38,000 rows/sec)
- ✅ Memory optimized (16 MB peak for 19k points)

### ZIP File Processing
- ✅ Automatic extraction
- ✅ Recursive CSV file discovery
- ✅ Multi-file processing
- ✅ Individual file error handling
- ✅ Automatic cleanup after processing

### Diagnostic Analysis (Streaming)
- ✅ **Catalyst Efficiency**: O2 sensor analysis, upstream/downstream comparison
- ✅ **Fuel Trim**: Short-term and long-term averages, stability analysis
- ✅ **O2 Sensors**: Voltage range, switching activity
- ✅ **Engine Health**: RPM, load, temperature monitoring
- ✅ **Overall Score**: Combined health assessment

### Data Storage
- ✅ TimescaleDB hypertables with 7-day chunks
- ✅ Automatic compression after 7 days (90% reduction)
- ✅ Retention policy (365 days)
- ✅ Time-series queries optimized

---

## Testing

### Test Scripts

#### CSV Upload
```bash
php bin/demo-api-upload var/tmp/data/trackLog-2025-oct.-23_12-00-00.csv
```

#### ZIP Upload
```bash
php bin/demo-api-upload-zip var/tmp/zipExport-2025-10-23_14-06-10.zip
```

#### View Diagnostics
```bash
php bin/show-diagnostics <trip_id>
```

#### TimescaleDB Statistics
```bash
php bin/demo-timescaledb-stats
```

### Performance Metrics

**Single CSV (19,706 data points):**
- Parse time: 26-28 seconds
- Throughput: ~720 points/sec
- Memory: 16 MB peak
- Storage: ~32 kB (compressed: ~3 kB after 7 days)

**ZIP with 1 CSV:**
- Total time: 26.07 seconds
- Memory: 16 MB peak
- Extraction: <0.1 seconds

---

## Production Deployment

### Memory Optimization
Use production mode to disable debug middleware:
```bash
php bin/parse-csv-optimized <file.csv>
```

Memory savings: **90% reduction** (42.5 MB vs 512 MB in dev mode)

### Configuration Files
- `config/packages/prod/framework.yaml`: Profiler disabled, APCu cache
- `config/packages/prod/doctrine.yaml`: Logging/profiling disabled
- Upload directory: `var/uploads/` (configurable in `services.yaml`)

### Security Notes
- ⚠️ Current implementation uses demo user (ID: 1)
- 🔒 TODO: Implement proper authentication (JWT)
- 🔒 TODO: Implement user-specific upload limits
- 🔒 TODO: Add rate limiting for API endpoints

---

## Next Steps

### Async Processing (TODO)
Install Symfony Messenger for async background processing:
```bash
composer require symfony/messenger
```

Then modify `TripController` to dispatch `ParseCsvMessage` instead of synchronous processing.

### Notifications (TODO)
- WebSocket/SSE for real-time progress updates
- Email notification when processing completes
- Webhook callbacks for integration

### Unit Tests (TODO)
- `TripDataServiceTest`: Bulk insert, time-series queries
- `OBD2CsvParserTest`: Validation, timestamps, streaming
- `OBD2ColumnMapperTest`: Column recognition, variants
- `StreamingDiagnosticAnalyzerTest`: Diagnostic calculations

---

## Error Handling

### Common Errors

**"No file uploaded"**
- Ensure `csv_file` field name is correct in multipart form data

**"Invalid CSV format"**
- CSV must have at least 3 columns
- CSV must contain timestamp column (Device Time or GPS Time)

**"No CSV files found in ZIP archive"**
- ZIP must contain at least one `.csv` or `.txt` file
- Files can be in subdirectories

**"User not found"**
- Demo user must exist in database
- Run fixtures or create user manually

**"Vehicle model not found"**
- At least one vehicle model must exist
- Create vehicle model before using API
