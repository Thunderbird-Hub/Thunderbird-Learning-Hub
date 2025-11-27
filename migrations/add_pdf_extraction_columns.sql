-- Add extracted PDF content storage to files table
ALTER TABLE files
ADD COLUMN extracted_html LONGTEXT NULL AFTER file_type_category,
ADD COLUMN extracted_images_json LONGTEXT NULL AFTER extracted_html;
