-- Add a toggle to hide rich post content on desktop views when PDF previews are in use
ALTER TABLE posts
    ADD COLUMN hide_content_on_desktop TINYINT(1) NOT NULL DEFAULT 0 AFTER content;
