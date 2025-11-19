<?php
/**
 * Generic Page Template
 * Copy/rename this file to create new pages.
 * Then drop your custom logic + markup into the container below.
 */

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/user_helpers.php';

// Load training helpers if available (keeps behavior consistent with index.php)
if (file_exists(__DIR__ . '/../includes/training_helpers.php')) {
    require_once __DIR__ . '/../includes/training_helpers.php';
}

// Set the page title used by header.php
$page_title = 'Training Analytics Dashboard';


// Include standard header (HTML <head>, nav, etc.)
include __DIR__ . '/../includes/header.php';
?>

<style>
.main-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
.dash-hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 8px; margin-bottom: 20px; }
.dash-hero h1 { margin: 0 0 8px 0; font-size: 24px; }
.dash-hero p { margin: 0; opacity: 0.9; }

/* Modern card and styling from training_dashboard.php */
.course-card { border: 1px solid #ddd; padding: 16px; margin: 10px 0; border-radius: 8px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.course-card h3 { margin: 0 0 8px 0; color: #333; }
.course-card p { margin: 4px 0; color: #666; }
.btn { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
.btn:hover { background: #5a6fd8; }
.btn-secondary { background: #6c757d; }
.btn-secondary:hover { background: #5a6268; }

/* Stats grid and card styling */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0; }
.stat-card { background: white; padding: 16px; border-radius: 8px; border-left: 4px solid #667eea; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.stat-number { font-size: 24px; font-weight: bold; color: #667eea; }
.stat-label { color: #666; font-size: 14px; margin-top: 4px; }

/* Content cards for detailed views */
.content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; margin-top: 12px; }
.content-card { position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; padding-bottom: 32px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
.content-card h4 { margin: 0 0 6px 0; font-size: 16px; color: #333; }

/* Analytics-specific styling (preserved and enhanced) */
.card.analytics-card {
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    border-radius: 8px;
    background: white;
    width: 100%;
    max-width: 100%;
    margin-right: 0;
    margin-left: 0;
    overflow: hidden;
}

.card-header.analytics-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    padding: 16px 20px;
    font-weight: 600;
}

.card.analytics-card .card-body {
    padding: 0;
}

.card.analytics-card .table-responsive {
    margin: 0;
}

.analytics-table th {
    background: #f8f9fa;
    font-size: 13px;
    color: #4b5563;
}

.course-progress-table th:nth-child(1),
.course-progress-table td:nth-child(1) {
    width: 25%; /* Content - 1st quarter */
}
.course-progress-table th:nth-child(2),
.course-progress-table td:nth-child(2) {
    width: 25%; /* Status - 2nd quarter */
}
.course-progress-table th:nth-child(3),
.course-progress-table td:nth-child(3) {
    width: 50%; /* Quiz - right half */
}

.course-progress-table {
    table-layout: fixed;
    width: 100%; /* Fill container */
}

.status-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-pill.completed { background: #d1e7dd; color: #0f5132; }
.status-pill.in-progress { background: #fff3cd; color: #856404; }
.status-pill.not-started { background: #e9ecef; color: #495057; }

/* Enhanced quiz display styling */
.quiz-section {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 12px;
    border: 1px solid #e5e7eb;
}

.quiz-latest {
    font-size: 13px;
    margin-bottom: 8px;
    color: #4b5563;
    padding: 8px 12px;
    background: #e3f2fd;
    border-radius: 4px;
    border-left: 3px solid #667eea;
}

.quiz-attempts-container {
    margin-top: 8px;
}

.quiz-attempts-table {
    font-size: 12px;
    background: white;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    table-layout: fixed;
    width: 100%;
}

/* Quiz attempts table column widths */
.quiz-attempts-table th:nth-child(1),
.quiz-attempts-table td:nth-child(1) { width: 50px; } /* Attempt # */
.quiz-attempts-table th:nth-child(2),
.quiz-attempts-table td:nth-child(2) { width: 80px; } /* Score */
.quiz-attempts-table th:nth-child(3),
.quiz-attempts-table td:nth-child(3) { width: 70px; } /* Status - thinner */
.quiz-attempts-table th:nth-child(4),
.quiz-attempts-table td:nth-child(4) { width: 150px; } /* Date - wider */
.quiz-attempts-table th:nth-child(5),
.quiz-attempts-table td:nth-child(5) { width: 80px; } /* Result */

.quiz-attempts-table th {
    background: #667eea;
    color: white;
    font-weight: 600;
    border: none;
    padding: 8px 12px;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
}

.quiz-attempts-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.quiz-attempts-table tr:last-child td {
    border-bottom: none;
}

.quiz-attempts-table tr:nth-child(even) {
    background: #f9fafb;
}

/* Quiz filter controls styling */
.quiz-controls {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.quiz-filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.quiz-filter-group label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.quiz-filter-select {
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 12px;
    background: white;
    color: #374151;
    min-width: 120px;
}

.quiz-filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}

.quiz-pagination-info {
    font-size: 12px;
    color: #6b7280;
    margin-left: auto;
    align-self: center;
}

.quiz-summary-stats {
    display: flex;
    gap: 12px;
    font-size: 12px;
}

.quiz-stat {
    display: flex;
    align-items: center;
    gap: 4px;
}

.quiz-stat.passed {
    color: #059669;
}

.quiz-stat.failed {
    color: #dc2626;
}

.quiz-stat.total {
    color: #6b7280;
}

.quiz-stat-number {
    font-weight: 700;
    font-size: 16px;
}

/* Enhanced stat tiles with modern styling */
.stat-tile {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    height: 100%;
    border-left: 4px solid #667eea;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.stat-tile h6 {
    font-size: 12px;
    color: #6c757d;
    letter-spacing: 0.02em;
    margin-bottom: 6px;
}
.stat-tile .value {
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
}
.stat-tile .subtext {
    font-size: 12px;
    color: #6c757d;
}

.table > :not(caption) > * > * { vertical-align: middle; }
</style>

<div class="main-container">
    <?php
// Page title
$page_title = 'Training Analytics Dashboard';

// Admin-only access
if (!is_admin() && !is_super_admin()) {
    echo "<div class='alert alert-danger mt-4'>Access denied. Admin privileges required.</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="dash-hero">
    <h1>📊 Training Analytics Dashboard</h1>
    <p>Admin analytics view for course assignments, completion rates, and user training performance.</p>
</div>

<?php
// -----------------------------------------------------
// SECTION 1: Course Overview (MVP foundation)
// -----------------------------------------------------

try {
    // Fetch all active courses
    $course_stmt = $pdo->query("
        SELECT id, name, department
        FROM training_courses
        WHERE is_active = 1
        ORDER BY name ASC
    ");

    $courses = $course_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="card analytics-card mb-4">
    <div class="card-header analytics-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Course Overview</h5>
        <span class="badge bg-secondary">Live snapshot</span>
    </div>
    <div class="card-body">

        <?php if (empty($courses)): ?>
            <p>No active training courses found.</p>
        <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover align-middle analytics-table mb-0">
                <thead>
                    <tr>
                        <th>Course Name</th>
                        <th>Department</th>
                        <th>Assigned</th>
                        <th>Completed</th>
                        <th>Completion Rate</th>
                        <th>View</th>
                    </tr>
                </thead>
                <tbody>

            <?php
            foreach ($courses as $course) {

                $course_id = intval($course['id']);

                // Count assigned users
                $assigned_stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT user_id) AS total_assigned
                    FROM user_training_assignments
                    WHERE course_id = ?
                ");
                $assigned_stmt->execute([$course_id]);
                $assigned = $assigned_stmt->fetch(PDO::FETCH_ASSOC)['total_assigned'] ?? 0;

                // Count completed users
                $completed_stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT user_id) AS total_completed
                    FROM training_history
                    WHERE course_id = ?
                      AND course_completed_date IS NOT NULL
                ");
                $completed_stmt->execute([$course_id]);
                $completed = $completed_stmt->fetch(PDO::FETCH_ASSOC)['total_completed'] ?? 0;

                // Compute completion %
                $rate = ($assigned > 0)
                    ? round(($completed / $assigned) * 100, 1)
                    : 0;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($course['name']); ?></td>
                    <td><?php echo htmlspecialchars($course['department']); ?></td>
                    <td><?php echo $assigned; ?></td>
                    <td><?php echo $completed; ?></td>
                    <td><?php echo $rate; ?>%</td>
                    <td>
                        <a href="?course_id=<?php echo $course_id; ?>" class="btn btn-outline-primary btn-sm">
                            View
                        </a>
                    </td>
                </tr>
            <?php } ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php
// -----------------------------------------------------
// SECTION 2 — COURSE DETAILS VIEW
// -----------------------------------------------------

if (isset($_GET['course_id']) && ($course_id = intval($_GET['course_id'])) > 0 && !isset($_GET['user_id'])) {

    // Fetch course info
    $course_stmt = $pdo->prepare("
        SELECT id, name, department, description
        FROM training_courses
        WHERE id = ?
    ");
    $course_stmt->execute([$course_id]);
    $course_info = $course_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course_info) {
        echo "<div class='alert alert-danger'>Course not found.</div>";
    } else {

        echo "<h3 class='mt-4 mb-3'>📘 Course: " . htmlspecialchars($course_info['name']) . "</h3>";

        // Fetch all assigned users for this course
        $assigned_stmt = $pdo->prepare("
            SELECT uta.user_id, u.name
            FROM user_training_assignments AS uta
            JOIN users AS u ON u.id = uta.user_id
            WHERE uta.course_id = ?
            ORDER BY u.name ASC
        ");
        $assigned_stmt->execute([$course_id]);
        $assigned_users = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Per-user totals will be computed using the same logic as get_overall_training_progress()
        // inside the Assigned Users loop, to keep percentages consistent with the trainee dashboard.

        
        
        if (empty($assigned_users)) {
            echo "<div class='alert alert-warning'>No users are assigned to this course.</div>";
        } else {

            echo "<div class='card analytics-card mb-4'>
                <div class='card-header analytics-header'><h5 class='mb-0'>Assigned Users</h5></div>
                <div class='card-body'>";

            // Overall course status counters
            $total_assigned    = 0;
            $total_completed   = 0;
            $total_in_progress = 0;
            $total_not_started = 0;

            // Collect table rows while we compute counts
            $rows_html = '';

            foreach ($assigned_users as $user) {

                $uid = (int)$user['user_id'];
                $total_assigned++;

                // Same formula as get_overall_training_progress(), scoped to this user+course
                $progress_stmt = $pdo->prepare("
                    SELECT
                        COUNT(DISTINCT tcc.id) AS total_items,
                        COUNT(DISTINCT CASE WHEN tp.status = 'completed'   THEN tcc.id END) AS completed_items,
                        COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) AS in_progress_items
                    FROM user_training_assignments uta
                    JOIN training_courses tc
                      ON tc.id = uta.course_id
                    JOIN training_course_content tcc
                      ON uta.course_id = tcc.course_id
                    LEFT JOIN training_progress tp
                      ON tcc.content_id = tp.content_id
                     AND tp.user_id     = uta.user_id
                     AND (
                            tcc.content_type = tp.content_type
                         OR tp.content_type = ''
                         OR tp.content_type IS NULL
                         )
                    WHERE uta.user_id      = ?
                      AND uta.course_id    = ?
                      AND tc.is_active     = 1
                      AND tcc.content_type = 'post'
                      AND (tcc.is_required = 1 OR tcc.is_required IS NULL)
                ");
                $progress_stmt->execute([$uid, $course_id]);
                $p = $progress_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $total_items       = (int)($p['total_items'] ?? 0);
                $completed_items   = (int)($p['completed_items'] ?? 0);
                $in_progress_items = (int)($p['in_progress_items'] ?? 0);

                $percent = $total_items > 0
                    ? round(($completed_items / $total_items) * 100)
                    : 0;

                // Clamp 0–100
                if ($percent < 0) {
                    $percent = 0;
                } elseif ($percent > 100) {
                    $percent = 100;
                }

                // Derive status from the same signals the dashboard uses:
                if ($total_items > 0 && $completed_items >= $total_items) {
                    $status = 'Completed';
                } elseif ($in_progress_items > 0 || $completed_items > 0) {
                    $status = 'In Progress';
                } else {
                    $status = 'Not Started';
                }

                // Bump counters
                if ($status === 'Completed') {
                    $total_completed++;
                } elseif ($status === 'In Progress') {
                    $total_in_progress++;
                } else {
                    $total_not_started++;
                }

                // Build table row HTML
                $rows_html .= '<tr>'
                    . '<td>' . htmlspecialchars($user['name']) . '</td>'
                    . '<td>' . $percent . '%</td>'
                    . '<td>' . $status . '</td>'
                    . '<td><a href="?course_id=' . $course_id . '&user_id=' . $uid . '" '
                    . 'class="btn btn-sm btn-outline-secondary">View User</a></td>'
                    . '</tr>';
            }

            // Summary block above the table using modern stats-grid
            echo "
                <div class='stats-grid mb-3'>
                    <div class='stat-card'>
                        <div class='stat-number'>{$total_assigned}</div>
                        <div class='stat-label'>Users Enrolled</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number' style='color: #f59e0b;'>{$total_in_progress}</div>
                        <div class='stat-label'>In Progress</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number' style='color: #10b981;'>{$total_completed}</div>
                        <div class='stat-label'>Completed</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number' style='color: #6b7280;'>{$total_not_started}</div>
                        <div class='stat-label'>Not Started</div>
                    </div>
                </div>
            ";

            // Now render the table with the collected rows
            echo "
                <div class='table-responsive'>
                    <table class='table table-bordered table-striped table-hover align-middle analytics-table mb-0'>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Completion %</th>
                                <th>Status</th>
                                <th>View</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$rows_html}
                        </tbody>
                    </table>
                </div>
                </div>
            </div>";
        }
    }
}


// -----------------------------------------------------
// SECTION 3 — INDIVIDUAL USER VIEW (WITH ATTEMPTS)
// -----------------------------------------------------

if (isset($_GET['course_id']) && isset($_GET['user_id']) &&
    ($course_id = intval($_GET['course_id'])) > 0 &&
    ($user_id = intval($_GET['user_id'])) > 0) {

    // Fetch user name
    $user_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user_name = $user_stmt->fetchColumn();

    if (!$user_name) {
        echo "<div class='alert alert-danger'>User not found.</div>";
    } else {

        echo "<h3 class='mt-4 mb-3'>👤 User: " . htmlspecialchars($user_name) . "</h3>";

        // Fetch course content items (for detailed row view)
        $content_stmt = $pdo->prepare("
            SELECT id, content_type, content_id
            FROM training_course_content
            WHERE course_id = ?
            ORDER BY training_order ASC
        ");
        $content_stmt->execute([$course_id]);
        $content_items = $content_stmt->fetchAll(PDO::FETCH_ASSOC);

        // High-level summary for this user + course
        // Uses the same logic as get_overall_training_progress(), but scoped
        // to this specific course + user and only required post items.
        $summary_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT tcc.id) AS total_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'completed'   THEN tcc.id END) AS completed_items,
                COUNT(DISTINCT CASE WHEN tp.status = 'in_progress' THEN tcc.id END) AS in_progress_items
            FROM user_training_assignments uta
            JOIN training_courses tc
              ON tc.id = uta.course_id
            JOIN training_course_content tcc
              ON uta.course_id = tcc.course_id
            LEFT JOIN training_progress tp
              ON tcc.content_id = tp.content_id
             AND tp.user_id     = uta.user_id
             AND (
                    tcc.content_type = tp.content_type
                 OR tp.content_type = ''
                 OR tp.content_type IS NULL
                 )
            WHERE uta.user_id      = ?
              AND uta.course_id    = ?
              AND tc.is_active     = 1
              AND tcc.content_type = 'post'
              AND (tcc.is_required = 1 OR tcc.is_required IS NULL)
        ");
        $summary_stmt->execute([$user_id, $course_id]);
        $s = $summary_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $sum_total       = (int)($s['total_items'] ?? 0);
        $sum_completed   = (int)($s['completed_items'] ?? 0);
        $sum_in_progress = (int)($s['in_progress_items'] ?? 0);
        $sum_not_started = max(0, $sum_total - $sum_completed - $sum_in_progress);

        $sum_percent = $sum_total > 0
            ? round(($sum_completed / $sum_total) * 100)
            : 0;

        echo "
        <div class='card analytics-card mb-4'>
            <div class='card-header analytics-header d-flex align-items-center justify-content-between'>
                <h5 class='mb-0'>Course Progress</h5>
                <span class='badge bg-secondary'>User snapshot</span>
            </div>
            <div class='card-body'>
                <div class='stats-grid mb-3'>
                    <div class='stat-card'>
                        <div class='stat-number'>{$sum_total}</div>
                        <div class='stat-label'>Required Items</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number' style='color: #f59e0b;'>{$sum_in_progress}</div>
                        <div class='stat-label'>In Progress</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number' style='color: #10b981;'>{$sum_completed}</div>
                        <div class='stat-label'>Completed</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>{$sum_percent}%</div>
                        <div class='stat-label'>Overall Completion</div>
                    </div>
                </div>
                <div class='table-responsive'>
                    <table class='table table-bordered table-striped table-hover align-middle course-progress-table analytics-table mb-0'>
                        <thead>
                            <tr>
                                <th>Content</th>
                                <th>Status</th>
                                <th>Quiz</th>
                            </tr>
                        </thead>
                        <tbody>
        ";


        foreach ($content_items as $item) {

            $ctype = $item['content_type'];
            $cid   = intval($item['content_id']);
            
            // Only show post rows in this table (hide categories, subcategories, etc.)
            if ($ctype !== 'post') {
                continue;
            }

            // Fetch progress entry
            // Some older rows may not have course_id set, so key off user + content
            $prog_stmt = $pdo->prepare("
                SELECT status, quiz_score, quiz_completed
                FROM training_progress
                WHERE user_id = ?
                  AND content_type = ?
                  AND content_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            
            $prog_stmt->execute([$user_id, $ctype, $cid]);
            $progress = $prog_stmt->fetch(PDO::FETCH_ASSOC);

            // Raw DB status (in_progress, completed, etc.)
            $status_raw = $progress['status'] ?? 'not_started';
            $status_lc  = strtolower((string)$status_raw);

            if ($status_lc === 'completed') {
                $status_label = 'Completed';
            } elseif ($status_lc === 'in_progress') {
                $status_label = 'In Progress';
            } elseif ($status_lc === 'not_started') {
                $status_label = 'Not Started';
            } else {
                // Fallback: capitalize whatever we got
                $status_label = ucfirst($status_lc ?: 'Not Started');
            }
            // Map status to pill style for the table
            $status_class = 'not-started';
            if ($status_lc === 'completed') {
                $status_class = 'completed';
            } elseif ($status_lc === 'in_progress') {
                $status_class = 'in-progress';
            }

            $status_html = "<span class='status-pill {$status_class}'>" . htmlspecialchars($status_label) . "</span>";
            

            // Fetch names of content
            $title = '';
            if ($ctype === 'post') {
                $post_stmt = $pdo->prepare("SELECT title FROM posts WHERE id = ?");
                $post_stmt->execute([$cid]);
                $title = $post_stmt->fetchColumn() ?: "Post #{$cid}";
            } else if ($ctype === 'quiz') {
                $quiz_stmt = $pdo->prepare("SELECT quiz_title FROM training_quizzes WHERE id = ?");
                $quiz_stmt->execute([$cid]);
                $title = $quiz_stmt->fetchColumn() ?: "Quiz #{$cid}";
            }

     // Quiz info if applicable (latest summary + full attempts list)
            // Works for:
            //  - content_type = 'quiz'  (quiz row in course content)
            //  - content_type = 'post'  (quiz attached to a post)
            $quiz_info         = '';
            $attempts_html     = '';
            $quiz_id_for_item  = null;

            if ($ctype === 'quiz') {
                // Direct quiz content
                $quiz_id_for_item = $cid;
            } elseif ($ctype === 'post') {
                // Quiz attached to this post (same pattern as training_dashboard)
                $quiz_lookup = $pdo->prepare("
                    SELECT id
                    FROM training_quizzes
                    WHERE content_id = ?
                      AND LOWER(COALESCE(content_type, '')) IN ('post', '')
                    LIMIT 1
                ");
                $quiz_lookup->execute([$cid]);
                $quiz_id_for_item = $quiz_lookup->fetchColumn() ?: null;
            }

            if ($quiz_id_for_item) {
                // Fetch attempts (latest first)
                                $attempt_stmt = $pdo->prepare("
                    SELECT id, attempt_number, score, status, completed_at
                    FROM user_quiz_attempts
                    WHERE user_id = ?
                      AND quiz_id = ?
                      AND status IN ('passed','failed')
                    ORDER BY attempt_number DESC
                ");

                $attempt_stmt->execute([$user_id, $quiz_id_for_item]);
                $attempts = $attempt_stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($attempts) {
                    $latest        = $attempts[0];
                    $latest_score  = (int)($latest['score'] ?? 0);
                    $latest_status = htmlspecialchars($latest['status'] ?? '', ENT_QUOTES, 'UTF-8');
                    $latest_date   = $latest['completed_at']
                        ? date('Y-m-d H:i', strtotime($latest['completed_at']))
                        : '—';

                    // Build filterable quiz attempts section
                    $quiz_unique_id = 'quiz_' . $quiz_id_for_item . '_' . $user_id;

                    $attempts_html .= "<div class='quiz-section' data-quiz-id='{$quiz_unique_id}'>";

                    // Summary stats at the top of quiz section
                    $passed_count = 0;
                    $failed_count = 0;
                    foreach ($attempts as $att) {
                        if (strtolower($att['status'] ?? '') === 'passed') $passed_count++;
                        if (strtolower($att['status'] ?? '') === 'failed') $failed_count++;
                    }

                    $attempts_html .= "<div class='quiz-summary-stats'>";
                    $attempts_html .= "<div class='quiz-stat passed'><span class='quiz-stat-number' id='passed-count-{$quiz_unique_id}'>{$passed_count}</span> Passed</div>";
                    $attempts_html .= "<div class='quiz-stat failed'><span class='quiz-stat-number' id='failed-count-{$quiz_unique_id}'>{$failed_count}</span> Failed</div>";
                    $attempts_html .= "<div class='quiz-stat total'><span class='quiz-stat-number'>" . count($attempts) . "</span> Total</div>";
                    $attempts_html .= "</div>";

                    // Filter controls
                    $attempts_html .= "<div class='quiz-controls'>";
                    $attempts_html .= "<div class='quiz-filter-group'>";
                    $attempts_html .= "<label>Status</label>";
                    $attempts_html .= "<select class='quiz-filter-select' id='status-filter-{$quiz_unique_id}'>";
                    $attempts_html .= "<option value='all'>All Attempts</option>";
                    $attempts_html .= "<option value='passed' selected>Passed Only</option>";
                    $attempts_html .= "<option value='failed'>Failed Only</option>";
                    $attempts_html .= "</select>";
                    $attempts_html .= "</div>";

                    $attempts_html .= "<div class='quiz-filter-group'>";
                    $attempts_html .= "<label>Show</label>";
                    $attempts_html .= "<select class='quiz-filter-select' id='page-size-{$quiz_unique_id}'>";
                    $attempts_html .= "<option value='5'>5 results</option>";
                    $attempts_html .= "<option value='10' selected>10 results</option>";
                    $attempts_html .= "<option value='25'>25 results</option>";
                    $attempts_html .= "<option value='50'>50 results</option>";
                    $attempts_html .= "<option value='999'>All results</option>";
                    $attempts_html .= "</select>";
                    $attempts_html .= "</div>";

                    $attempts_html .= "<div class='quiz-pagination-info' id='pagination-info-{$quiz_unique_id}'>10 results</div>";
                    $attempts_html .= "</div>";

                    // Table
                    $attempts_html .= "<div class='quiz-attempts-container'>";
                    $attempts_html .= "<table class='quiz-attempts-table'>";
                    $attempts_html .= "<thead><tr>"
                                    . "<th>#</th>"
                                    . "<th>Score</th>"
                                    . "<th>Status</th>"
                                    . "<th>Date</th>"
                                    . "<th>Result</th>"
                                    . "</tr></thead>";
                    $attempts_html .= "<tbody id='quiz-table-{$quiz_unique_id}'>";

                    foreach ($attempts as $att) {
                        $att_id      = (int)$att['id'];
                        $att_num     = (int)($att['attempt_number'] ?? 0);
                        $att_score   = (int)($att['score'] ?? 0);
                        $att_status_raw = $att['status'] ?? '';
                        $att_status  = htmlspecialchars($att_status_raw, ENT_QUOTES, 'UTF-8');
                        $att_date    = $att['completed_at']
                            ? date('Y-m-d H:i', strtotime($att['completed_at']))
                            : '—';

                        $result_link = '';
                        $status_for_button = strtolower(trim($att_status_raw));
                        if (in_array($status_for_button, ['passed', 'failed'], true)) {
                            $result_link = "<a href='/training/quiz_results.php?attempt_id=" . intval($att_id) . "&admin_view=1' class='btn btn-xs btn-primary'>View</a>";
                        } else {
                            $result_link = "—";
                        }

                        $attempts_html .= "<tr>"
                            . "<td>{$att_num}</td>"
                            . "<td>{$att_score}%</td>"
                            . "<td>{$att_status}</td>"
                            . "<td>{$att_date}</td>"
                            . "<td>{$result_link}</td>"
                            . "</tr>";
                    }

                    $attempts_html .= "</tbody></table></div></div>";
                } else {
                    $quiz_info = "<div class='quiz-section'><div class='quiz-latest'>No completed attempts yet</div></div>";
                }
            }

                        echo "
                <tr>
                    <td>" . htmlspecialchars($title) . "</td>
                    <td>{$status_html}</td>
                    <td>{$quiz_info}{$attempts_html}</td>
                </tr>
            ";

        }

        echo "
                </tbody>
            </table>
            </div>
        </div>
        ";
    }
}
?>

</div>

<script>
// Quiz filtering and pagination functionality
function initializeQuizFilters(quizId) {
    const statusFilter = document.getElementById(`status-filter-${quizId}`);
    const pageSizeFilter = document.getElementById(`page-size-${quizId}`);
    const tableBody = document.getElementById(`quiz-table-${quizId}`);
    const paginationInfo = document.getElementById(`pagination-info-${quizId}`);
    const passedStat = document.getElementById(`passed-count-${quizId}`);
    const failedStat = document.getElementById(`failed-count-${quizId}`);

    if (!statusFilter || !tableBody) return;

    // Get all attempt data from table rows
    const allAttempts = Array.from(tableBody.children).map(row => {
        const cells = row.children;
        return {
            row: row,
            attempt: parseInt(cells[0].textContent),
            score: parseInt(cells[1].textContent),
            status: cells[2].textContent.trim().toLowerCase(),
            date: cells[3].textContent,
            resultLink: cells[4].innerHTML
        };
    });

    function updateDisplay() {
        const statusFilterValue = statusFilter.value;
        const pageSize = parseInt(pageSizeFilter ? pageSizeFilter.value : '10');

        // Filter attempts
        let filteredAttempts = allAttempts.filter(attempt => {
            if (statusFilterValue === 'all') return true;
            return attempt.status === statusFilterValue;
        });

        // Update summary stats
        const passedCount = allAttempts.filter(a => a.status === 'passed').length;
        const failedCount = allAttempts.filter(a => a.status === 'failed').length;

        if (passedStat) passedStat.textContent = passedCount;
        if (failedStat) failedStat.textContent = failedCount;

        // Pagination
        const startIndex = 0;
        const endIndex = Math.min(startIndex + pageSize, filteredAttempts.length);

        // Clear table
        tableBody.innerHTML = '';

        // Show paginated results
        const paginatedAttempts = filteredAttempts.slice(startIndex, endIndex);

        if (paginatedAttempts.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="5" style="text-align: center; color: #6b7280; padding: 20px;">No attempts found for this filter</td>';
            tableBody.appendChild(emptyRow);

            if (paginationInfo) {
                paginationInfo.textContent = '0 results';
            }
        } else {
            paginatedAttempts.forEach(attempt => {
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td>${attempt.attempt}</td>
                    <td>${attempt.score}%</td>
                    <td>${attempt.status.charAt(0).toUpperCase() + attempt.status.slice(1)}</td>
                    <td>${attempt.date}</td>
                    <td>${attempt.resultLink}</td>
                `;
                tableBody.appendChild(newRow);
            });

            if (paginationInfo) {
                if (filteredAttempts.length <= pageSize) {
                    paginationInfo.textContent = `${filteredAttempts.length} results`;
                } else {
                    paginationInfo.textContent = `${endIndex} of ${filteredAttempts.length} results`;
                }
            }
        }
    }

    // Add event listeners
    statusFilter.addEventListener('change', updateDisplay);
    if (pageSizeFilter) {
        pageSizeFilter.addEventListener('change', updateDisplay);
    }

    // Initial display
    updateDisplay();
}

// Initialize all quiz filters when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Find all quiz sections and initialize them
    const quizSections = document.querySelectorAll('[data-quiz-id]');
    quizSections.forEach(section => {
        const quizId = section.getAttribute('data-quiz-id');
        initializeQuizFilters(quizId);
    });
});
</script>

<?php
// Standard footer (includes your latest updates widget, bug report button, etc.)
include __DIR__ . '/../includes/footer.php';
?>
