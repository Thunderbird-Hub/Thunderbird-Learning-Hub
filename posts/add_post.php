<?php
/**
 * Add Post Form TEST
 * Creates a new post with rich text content and file uploads
 * Updated: 2025-11-05 (Removed hardcoded user references - database-only users)
 *
 * FIXED: Removed hardcoded user array references that were interfering with database authentication
 * - User selection in sharing form now uses database queries instead of $GLOBALS['USERS']
 * - Complete database-driven user system integration
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';
require_once __DIR__ . '/../includes/training_helpers.php';
require_once __DIR__ . '/../includes/department_helpers.php';
require_once __DIR__ . '/../includes/pdf_extraction.php';

$page_title = 'Add Post';

// Check if user can create posts
if (!can_create_posts()) {
    if (is_training_user()) {
        $error_message = 'Training users cannot create posts. You must complete your assigned training materials first.';
    } else {
        $error_message = 'Only administrators can create posts. Please contact an administrator if you need to create content.';
    }

    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="container">
        <div class="card">
            <div class="card-content" style="padding: 40px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">🚫</div>
                <h2>Access Denied</h2>
                <p style="color: #6c757d; margin-bottom: 24px;"><?php echo htmlspecialchars($error_message); ?></p>
                <a href="/index.php" class="btn btn-primary">← Back to Home</a>
                <?php if (is_training_user()): ?>
                <br><br>
                <a href="training_dashboard.php" class="btn btn-secondary">🎓 View Training Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}
$error_message = '';
$subcategory_id = isset($_GET['subcategory_id']) ? intval($_GET['subcategory_id']) : 0;
$subcategory = null;

if ($subcategory_id <= 0) {
    header('Location: /index.php');
    exit;
}

// Check if subcategory visibility column exists
$subcategory_has_visibility = false;
try {
    $test_query = $pdo->query("SELECT visibility FROM subcategories LIMIT 1");
    $subcategory_has_visibility = true;
} catch (PDOException $e) {
    $subcategory_has_visibility = false;
}

// Check if posts table supports department sharing
$shared_departments_supported = false;
try {
    $pdo->query("SELECT shared_departments FROM posts LIMIT 1");
    $shared_departments_supported = true;
} catch (PDOException $e) {
    $shared_departments_supported = false;
}

$all_departments = $shared_departments_supported ? get_all_departments($pdo) : [];

// Fetch subcategory info for breadcrumb
try {
    if ($subcategory_has_visibility) {
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS category_name
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            WHERE s.id = ?
        ");
    }
    $stmt->execute([$subcategory_id]);
    $subcategory = $stmt->fetch();

    if (!$subcategory) {
        $error_message = 'Subcategory not found.';
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subcategory) {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $privacy = isset($_POST['privacy']) ? $_POST['privacy'] : 'public';
    $shared_departments = isset($_POST['shared_departments']) ? array_map('intval', $_POST['shared_departments']) : [];

    // Check if files are being uploaded
    $has_files = (!empty($_FILES['files']['name'][0]) && $_FILES['files']['error'][0] == UPLOAD_ERR_OK) ||
                 (!empty($_FILES['preview_files']['name'][0]) && $_FILES['preview_files']['error'][0] == UPLOAD_ERR_OK);

// Validation
    if (empty($title)) {
        $error_message = 'Post title is required.';
    } elseif (strlen($title) > 500) {
        $error_message = 'Post title must be 500 characters or less.';
    } elseif (empty(strip_tags($content)) && !$has_files) {
        $error_message = 'Post content or at least one file attachment is required.';
    } elseif ($privacy === 'shared' && !$shared_departments_supported) {
        $error_message = 'Department sharing is required for shared privacy. Please add the shared_departments column.';
    } elseif ($privacy === 'shared' && empty($shared_departments)) {
        $error_message = 'Please select at least one department to share with.';
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Prepare shared_with JSON
            $shared_with_json = null;

            $shared_departments_json = null;
            if ($shared_departments_supported && $privacy === 'shared' && !empty($shared_departments)) {
                $shared_departments_json = json_encode($shared_departments);
            }

            // Insert post
            if ($shared_departments_supported) {
                $stmt = $pdo->prepare("INSERT INTO posts (subcategory_id, user_id, title, content, privacy, shared_with, shared_departments) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subcategory_id, $_SESSION['user_id'], $title, $content, $privacy, $shared_with_json, $shared_departments_json]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO posts (subcategory_id, user_id, title, content, privacy, shared_with) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subcategory_id, $_SESSION['user_id'], $title, $content, $privacy, $shared_with_json]);
            }
            $post_id = $pdo->lastInsertId();

            // Handle regular file uploads
            if (!empty($_FILES['files']['name'][0])) {
                $upload_errors = [];
                $file_count = count($_FILES['files']['name']);

                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['files']['error'][$i] == UPLOAD_ERR_OK) {
                        $original_filename = $_FILES['files']['name'][$i];
                        $tmp_name = $_FILES['files']['tmp_name'][$i];
                        $file_size = $_FILES['files']['size'][$i];
                        $file_type = $_FILES['files']['type'][$i];

                        // Validate file size
                        if ($file_size > MAX_FILE_SIZE) {
                            $upload_errors[] = "File '{$original_filename}' exceeds 20 MB limit.";
                            continue;
                        }

                        // Sanitize filename
                        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
                        $stored_filename = time() . '_' . $safe_filename . '.' . $file_ext;

                        // Determine if image or file
                        $is_image = in_array($file_ext, IMAGE_EXTENSIONS);
                        $upload_dir = $is_image ? UPLOAD_PATH_IMAGES : UPLOAD_PATH_FILES;
                        $file_path = $upload_dir . $stored_filename;

                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $extracted = extract_pdf_content($file_type, $original_filename, $file_path, $stored_filename);
                            // Insert file record as 'download' type
                            $stmt = $pdo->prepare("
                                INSERT INTO files (post_id, original_filename, stored_filename, file_path, file_size, file_type, file_type_category, extracted_html, extracted_images_json)
                                VALUES (?, ?, ?, ?, ?, ?, 'download', ?, ?)
                            ");
                            $stmt->execute([
                                $post_id,
                                $original_filename,
                                $stored_filename,
                                $file_path,
                                $file_size,
                                $file_type,
                                $extracted['extracted_html'],
                                $extracted['extracted_images_json']
                            ]);
                        } else {
                            $upload_errors[] = "Failed to upload '{$original_filename}'.";
                        }
                    }
                }

                if (!empty($upload_errors)) {
                    $error_message = implode('<br>', $upload_errors);
                }
            }

            // Handle preview file uploads (PDF/DOCX for inline viewing)
            if (!empty($_FILES['preview_files']['name'][0])) {
                $preview_errors = [];
                $preview_file_count = count($_FILES['preview_files']['name']);

                for ($i = 0; $i < $preview_file_count; $i++) {
                    if ($_FILES['preview_files']['error'][$i] == UPLOAD_ERR_OK) {
                        $original_filename = $_FILES['preview_files']['name'][$i];
                        $tmp_name = $_FILES['preview_files']['tmp_name'][$i];
                        $file_size = $_FILES['preview_files']['size'][$i];
                        $file_type = $_FILES['preview_files']['type'][$i];

                        // Validate file type (should only be PDF or DOCX)
                        $file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                        if (!in_array($file_ext, ['pdf', 'doc', 'docx'])) {
                            $preview_errors[] = "File '{$original_filename}' is not a supported preview format. Only PDF and Word documents are allowed.";
                            continue;
                        }

                        // Validate file size
                        if ($file_size > MAX_FILE_SIZE) {
                            $preview_errors[] = "File '{$original_filename}' exceeds 20 MB limit.";
                            continue;
                        }

                        // Sanitize filename
                        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
                        $stored_filename = time() . '_preview_' . $safe_filename . '.' . $file_ext;

                        // Store preview files in a separate directory
                        $upload_dir = UPLOAD_PATH_FILES . 'preview/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $file_path = $upload_dir . $stored_filename;

                        // Move uploaded file
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $extracted = extract_pdf_content($file_type, $original_filename, $file_path, $stored_filename);
                            // Insert file record as 'preview' type
                            $stmt = $pdo->prepare("
                                INSERT INTO files (post_id, original_filename, stored_filename, file_path, file_size, file_type, file_type_category, extracted_html, extracted_images_json)
                                VALUES (?, ?, ?, ?, ?, ?, 'preview', ?, ?)
                            ");
                            $stmt->execute([
                                $post_id,
                                $original_filename,
                                $stored_filename,
                                $file_path,
                                $file_size,
                                $file_type,
                                $extracted['extracted_html'],
                                $extracted['extracted_images_json']
                            ]);
                        } else {
                            $preview_errors[] = "Failed to upload preview file '{$original_filename}'.";
                        }
                    }
                }

                if (!empty($preview_errors)) {
                    if (!empty($error_message)) {
                        $error_message .= '<br>';
                    }
                    $error_message .= implode('<br>', $preview_errors);
                }
            }

            // Commit transaction
            $pdo->commit();

            // Redirect to post detail page
            header('Location: post.php?id=' . $post_id);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database Error: " . $e->getMessage());
            $error_message = "Database error occurred. Please try again.";
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <?php if ($subcategory): ?>
        <div class="breadcrumb">
            <a href="/index.php">Home</a>
            <span>></span>
            <span><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
            <span>></span>
            <a href="/categories/subcategory.php?id=<?php echo $subcategory_id; ?>"><?php echo htmlspecialchars($subcategory['name']); ?></a>
            <span>></span>
            <span class="current">Add Post</span>
        </div>
    <?php endif; ?>

    <?php if (!$subcategory): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <a href="/index.php" class="btn btn-primary">Back to Home</a>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Create New Post</h2>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_post.php?subcategory_id=<?php echo $subcategory_id; ?>" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title" class="form-label">Post Title *</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        class="form-input"
                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                        required
                        maxlength="500"
                        placeholder="Enter a descriptive title for your post"
                    >
                    <div class="form-hint">Max 500 characters</div>
                </div>

                <div class="form-group">
                    <label for="content" class="form-label">Content</label>
                    <textarea id="content" name="content" class="form-textarea"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                    <div class="form-hint">Use the toolbar to format your content. (Required unless attaching files)</div>
                </div>

                <div class="form-group">
                    <label for="privacy" class="form-label">Privacy Settings *</label>
                    <select id="privacy" name="privacy" class="form-select" onchange="toggleSharedUsers()">
                        <?php
                        // Use all options for Super Admins, regular options for others
                        $privacy_options = is_super_admin() ? $GLOBALS['PRIVACY_OPTIONS_ALL'] : $GLOBALS['PRIVACY_OPTIONS'];

                        // Determine default privacy based on subcategory visibility
                        $default_privacy = 'public';
                        if ($subcategory_has_visibility && $subcategory) {
                            $subcat_visibility = $subcategory['visibility'] ?? 'public';
                            // Map subcategory visibility to post privacy
                            switch ($subcat_visibility) {
                                case 'public':
                                    $default_privacy = 'public';
                                    break;
                                case 'hidden':
                                    $default_privacy = 'private';
                                    break;
                                case 'restricted':
                                    $default_privacy = 'shared';
                                    break;
                                case 'it_only':
                                    $default_privacy = 'it_only';
                                    break;
                                default:
                                    $default_privacy = 'public';
                            }
                        }

                        // Determine current privacy to select
                        $current_privacy = isset($_POST['privacy']) ? $_POST['privacy'] : $default_privacy;

                        foreach ($privacy_options as $value => $label):
                        ?>
                            <option value="<?php echo $value; ?>" <?php echo ($current_privacy == $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Defaults to: <?php
                        $default_labels = ['public' => 'Public', 'private' => 'Private', 'shared' => 'Shared', 'it_only' => 'IT Only'];
                        echo $default_labels[$default_privacy] ?? 'Public';
                    ?> (matching subcategory visibility)</div>
                </div>

                <?php if (!$shared_departments_supported): ?>
                    <div style="background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 10px 12px; border-radius: 4px; margin-bottom: 12px;">
                        Department sharing isn't available yet. Add the <strong>shared_departments</strong> column to the <strong>posts</strong> table (see migrations/add_department_visibility_columns.sql) to enable it.
                    </div>
                <?php endif; ?>

                <div class="form-group" id="shared_users_group" style="display: none;">
                    <label class="form-label">Share With Departments *</label>
                    <div class="checkbox-group">
                        <?php if (empty($all_departments)): ?>
                            <div style="color: #999; text-align: center; padding: 10px;">No departments found</div>
                        <?php else: ?>
                            <?php foreach ($all_departments as $department): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="shared_departments[]" value="<?php echo $department['id']; ?>" <?php echo (isset($_POST['shared_departments']) && in_array($department['id'], array_map('intval', $_POST['shared_departments']))) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($department['name']); ?>
                                    <span style="color: #666; font-size: 12px; margin-left: 4px;">(Members: <?php echo intval($department['member_count'] ?? 0); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="files" class="form-label">Attach Files for User Download (Optional)</label>
                    <input
                        type="file"
                        id="files"
                        name="files[]"
                        class="form-file"
                        multiple
                        accept="*/*"
                    >
                    <div class="form-hint">Max 20 MB per file. These files will be available for users to download.</div>
                </div>

                <div class="form-group">
                    <label for="preview_files" class="form-label">📄 Add PDF/DOCX Files for Inline Preview (Optional)</label>
                    <input
                        type="file"
                        id="preview_files"
                        name="preview_files[]"
                        class="form-file"
                        multiple
                        accept=".pdf,.doc,.docx"
                    >
                    <div class="form-hint">PDF and Word documents that will display inline in the post for easy viewing without downloading. Max 20 MB per file.</div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Create Post</button>
                    <a href="/categories/subcategory.php?id=<?php echo $subcategory_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- TinyMCE Rich Text Editor -->
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    function toggleSharedUsers() {
        const privacy = document.getElementById('privacy').value;
        const sharedUsersGroup = document.getElementById('shared_users_group');

        if (privacy === 'shared') {
            sharedUsersGroup.style.display = 'block';
        } else {
            sharedUsersGroup.style.display = 'none';
        }
    }

    tinymce.init({
        selector: '#content',
        license_key: 'gpl',
        height: 400,
        menubar: false,
        plugins: 'lists link code table textcolor colorpicker image',
        toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist | link | table | image | code | removeformat',
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
        branding: false,
        promotion: false,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        images_upload_url: 'upload_image.php',
        automatic_uploads: true
    });

    // Initialize privacy field
    document.addEventListener('DOMContentLoaded', function() {
        toggleSharedUsers();
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>