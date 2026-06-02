"""
Error Handler Wrapper for Analysis Scripts
Provides consistent error handling, logging, and user feedback
"""

import sys
import traceback
import json
from datetime import datetime

class AnalysisError(Exception):
    """Custom exception for analysis errors"""
    pass

class ErrorHandler:
    def __init__(self, script_name):
        self.script_name = script_name
        self.error_log_file = "logs/error_log.json"
        
    def log_error(self, error_type, error_message, traceback_str=""):
        """Log error to error log file"""
        try:
            # Read existing errors
            try:
                with open(self.error_log_file, 'r', encoding='utf-8') as f:
                    errors = json.load(f)
            except:
                errors = []
            
            # Add new error
            error_entry = {
                "timestamp": datetime.now().isoformat(),
                "script": self.script_name,
                "error_type": error_type,
                "message": error_message,
                "traceback": traceback_str
            }
            errors.append(error_entry)
            
            # Keep last 100 errors
            if len(errors) > 100:
                errors = errors[-100:]
            
            # Write back
            with open(self.error_log_file, 'w', encoding='utf-8') as f:
                json.dump(errors, f, indent=4)
                
        except Exception as e:
            print(f"Failed to log error: {e}", file=sys.stderr)
    
    def handle_error(self, error, exit_on_error=False):
        """Handle an error with logging and user-friendly message"""
        error_type = type(error).__name__
        error_message = str(error)
        traceback_str = traceback.format_exc()
        
        # Log the error
        self.log_error(error_type, error_message, traceback_str)
        
        # Print user-friendly message
        print(f"\n❌ ERROR in {self.script_name}", file=sys.stderr)
        print(f"Type: {error_type}", file=sys.stderr)
        print(f"Message: {error_message}", file=sys.stderr)
        
        if exit_on_error:
            print(f"\nScript terminated due to critical error.", file=sys.stderr)
            sys.exit(1)
        
        return False
    
    def safe_execute(self, func, *args, **kwargs):
        """Execute a function with error handling"""
        try:
            return func(*args, **kwargs), None
        except Exception as e:
            self.handle_error(e)
            return None, e
    
    def validate_file_exists(self, filepath, file_description="File"):
        """Validate that a required file exists"""
        import os
        if not os.path.exists(filepath):
            raise AnalysisError(f"{file_description} not found: {filepath}")
        return True
    
    def validate_directory_writable(self, dirpath):
        """Validate that a directory is writable"""
        import os
        if not os.path.exists(dirpath):
            try:
                os.makedirs(dirpath, exist_ok=True)
            except Exception as e:
                raise AnalysisError(f"Cannot create directory {dirpath}: {e}")
        
        if not os.access(dirpath, os.W_OK):
            raise AnalysisError(f"Directory not writable: {dirpath}")
        
        return True

def create_error_handler(script_name):
    """Factory function to create error handler"""
    return ErrorHandler(script_name)
