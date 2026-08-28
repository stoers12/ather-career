<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/error_reporting.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Project.php';

const CONTACT_NAME_MAX_LENGTH = 100;
const CONTACT_EMAIL_MAX_LENGTH = 255;
const CONTACT_MESSAGE_MAX_LENGTH = 5000;
const CONTACT_RATE_LIMIT_ATTEMPTS = 3;
const CONTACT_RATE_LIMIT_WINDOW_SECONDS = 900;

startApplicationSession();

// Session data is stored on the server and referenced by the session ID cookie.
if (!isset($_SESSION['page_views'])) {
    $_SESSION['page_views'] = 0;
}
$_SESSION['page_views']++;
$pageViews = $_SESSION['page_views'];

// This harmless cookie is stored on the visitor's browser to remember a previous visit.
$hasVisited = isset($_COOKIE['portfolio_visited']);
setcookie('portfolio_visited', '1', [
    'expires' => time() + (365 * 24 * 60 * 60),
    'path' => '/',
    'secure' => applicationSessionCookieIsSecure(),
    'httponly' => false,
    'samesite' => 'Lax',
]);

$portfolioTitle = 'My Portfolio';
$major = 'Artificial Intelligence And Data Science';
$footerYear = 2026;

$projects = [];
$personalInfo = [
    'full_name' => 'My Portfolio',
    'professional_title' => $major,
    'email' => '',
    'phone_primary' => '',
    'phone_secondary' => '',
    'location' => '',
    'about_me' => 'This is the about section.',
    'work_description' => '',
    'linkedin_url' => '',
    'github_url' => '',
    'instagram_url' => '',
    'facebook_url' => '',
    'website_url' => '',
    'profile_image_path' => null,
];
$skills = [];
$databaseError = '';
$database = null;

try {
    $database = getDatabaseConnection();
    $projectStatement = $database->prepare(
        'SELECT id, title, category, description, github_url, image_path FROM projects ORDER BY created_at ASC, id ASC'
    );
    $projectStatement->execute();
    $projectRows = $projectStatement->fetchAll();
    foreach ($projectRows as $projectRow) {
        $projects[] = new Project(
            (int) $projectRow['id'],
            $projectRow['title'],
            $projectRow['category'],
            $projectRow['description'],
            $projectRow['github_url'],
            $projectRow['image_path']
        );
    }
    $profileStatement = $database->query('SELECT full_name, professional_title, email, phone_primary, phone_secondary, location, about_me, work_description, linkedin_url, github_url, instagram_url, facebook_url, website_url, profile_image_path FROM personal_info ORDER BY id ASC LIMIT 1');
    $savedProfile = $profileStatement->fetch();
    if ($savedProfile !== false) {
        $personalInfo = array_merge($personalInfo, $savedProfile);
    }
    $skills = $database->query('SELECT skill_name FROM skills ORDER BY created_at ASC, id ASC')->fetchAll();
} catch (PDOException | DatabaseConfigurationException $exception) {
    reportApplicationError($exception, 'index.php', 'portfolio_load');
    $databaseError = 'Projects are temporarily unavailable.';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function displayProjectCard(Project $project): void
{
    echo '<article>';
    if ($project->getImagePath() !== null) {
        echo '<img class="project-image" src="' . escapeHtml($project->getImagePath()) . '" alt="' . escapeHtml($project->getTitle()) . '">';
    }
    echo '<span class="badge">' . escapeHtml($project->getCategory()) . '</span>';
    echo '<h3>' . escapeHtml($project->getTitle()) . '</h3>';
    echo '<p>' . escapeHtml($project->getDescription()) . '</p>';
    echo '<a href="' . escapeHtml($project->getGithubUrl()) . '" target="_blank" rel="noopener noreferrer">View on GitHub</a>';
    echo '</article>';
}

$formErrors = [];
$formSuccess = isset($_SESSION['contact_success_flash']) && is_string($_SESSION['contact_success_flash'])
    ? $_SESSION['contact_success_flash']
    : '';
unset($_SESSION['contact_success_flash']);
$name = '';
$email = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) && is_string($_POST['message']) ? trim($_POST['message']) : '';

    if ($name === '') {
        $formErrors[] = 'Name is required.';
    } elseif (($nameLength = utf8CharacterLength($name)) === null || $nameLength > CONTACT_NAME_MAX_LENGTH) {
        $formErrors[] = 'Name must be 100 characters or fewer.';
    }

    if ($email === '') {
        $formErrors[] = 'Email is required.';
    } elseif (($emailLength = utf8CharacterLength($email)) === null || $emailLength > CONTACT_EMAIL_MAX_LENGTH) {
        $formErrors[] = 'Email must be 255 characters or fewer.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formErrors[] = 'Please enter a valid email address.';
    }

    if ($message === '') {
        $formErrors[] = 'Message is required.';
    } elseif (($messageLength = utf8CharacterLength($message)) === null || $messageLength > CONTACT_MESSAGE_MAX_LENGTH) {
        $formErrors[] = 'Message must be 5000 characters or fewer.';
    }

    if ($formErrors === []) {
        try {
            $rateLimit = consumeRateLimit('contact', rateLimitClientIp(), CONTACT_RATE_LIMIT_ATTEMPTS, CONTACT_RATE_LIMIT_WINDOW_SECONDS);
        } catch (Throwable $exception) {
            reportApplicationError($exception, 'index.php', 'contact_rate_limit');
            http_response_code(503);
            $formErrors[] = 'Messages are temporarily unavailable. Please try again later.';
            $rateLimit = null;
        }

        if ($rateLimit === null) {
            // The safe error response was set above; do not accept an unthrottled submission.
        } elseif (!$rateLimit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $rateLimit['retry_after']);
            $formErrors[] = 'Please wait before sending another message.';
        } elseif ($database instanceof PDO) {
            try {
                $messageStatement = $database->prepare(
                    'INSERT INTO messages (name, email, message)
                     VALUES (:name, :email, :message)'
                );
                $messageStatement->execute([
                    'name' => $name,
                    'email' => $email,
                    'message' => $message,
                ]);
                $_SESSION['contact_success_flash'] = 'Message submitted successfully.';
                header('Location: index.php#contact', true, 303);
                exit;
            } catch (PDOException $exception) {
                reportApplicationError($exception, 'index.php', 'contact_submit');
                $formErrors[] = 'The message could not be saved right now.';
            }
        } else {
            $formErrors[] = 'The message could not be saved right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($personalInfo['full_name']); ?> - Portfolio</title>
    <link rel="stylesheet" href="style.css?v=2">
    <link rel="icon" type="image/png" href="ChatGPT-Image-Apr-14-2025-065043-PM.png">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header">
        <nav class="container site-nav" aria-label="Main navigation">
            <a class="brand" href="#home"><?php echo escapeHtml($personalInfo['full_name']); ?></a>
            <button class="menu-toggle" id="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu" aria-label="Open navigation">Menu</button>
            <div class="nav-links" id="site-menu">
                <a href="#home">Home</a><a href="#about">About</a><a href="#skills">Skills</a><a href="#projects">Projects</a><a href="#contact">Contact</a><a class="nav-admin" href="login.php">Admin Login</a>
            </div>
        </nav>
    </header>

    <main id="main-content">
        <section class="hero" id="home"><div class="container hero-grid"><div class="hero-copy">
            <p class="eyebrow">Hello, I'm</p><h1><?php echo escapeHtml($personalInfo['full_name']); ?></h1>
            <p class="hero-title"><?php echo escapeHtml($personalInfo['professional_title']); ?></p>
            <p class="hero-description"><?php echo escapeHtml($personalInfo['work_description'] ?: $personalInfo['about_me']); ?></p>
            <?php if ($personalInfo['location'] !== ''): ?><p class="location"><?php echo escapeHtml($personalInfo['location']); ?></p><?php endif; ?>
            <div class="hero-actions"><a class="btn btn-primary" href="#projects">View My Projects</a><a class="btn btn-outline-light" href="#contact">Contact Me</a></div>
            <div class="social-links hero-social"><?php foreach (['github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn', 'website_url' => 'Website'] as $field => $label): ?><?php if ($personalInfo[$field] !== ''): ?><a href="<?php echo escapeHtml($personalInfo[$field]); ?>" target="_blank" rel="noopener noreferrer"><?php echo $label; ?></a><?php endif; ?><?php endforeach; ?></div>
        </div><div class="hero-visual"><?php if (!empty($personalInfo['profile_image_path'])): ?><div class="profile-photo-frame"><img class="profile-photo" src="<?php echo escapeHtml($personalInfo['profile_image_path']); ?>" alt="<?php echo escapeHtml($personalInfo['full_name']); ?>"></div><?php else: ?><?php $initials = strtoupper(substr($personalInfo['full_name'], 0, 1) . (strrpos($personalInfo['full_name'], ' ') !== false ? substr($personalInfo['full_name'], strrpos($personalInfo['full_name'], ' ') + 1, 1) : '')); ?><span><?php echo escapeHtml($initials); ?></span><?php endif; ?></div></div></section>

        <section class="about-section" id="about"><div class="container about-grid"><div><p class="section-label">ABOUT</p><h2>About Me</h2><?php if ($personalInfo['about_me'] !== ''): ?><p><?php echo nl2br(escapeHtml($personalInfo['about_me'])); ?></p><?php endif; ?><?php if ($personalInfo['work_description'] !== ''): ?><p><?php echo nl2br(escapeHtml($personalInfo['work_description'])); ?></p><?php endif; ?></div><div class="quick-info"><h3>Quick Info</h3><?php foreach (['location' => 'Location', 'email' => 'Email', 'phone_primary' => 'Phone', 'professional_title' => 'Role'] as $field => $label): ?><?php if ($personalInfo[$field] !== ''): ?><div class="info-row"><span><?php echo $label; ?></span><strong><?php echo escapeHtml($personalInfo[$field]); ?></strong></div><?php endif; ?><?php endforeach; ?></div></div></section>

        <?php if ($skills !== []): ?><section class="skills-section" id="skills"><div class="container"><p class="section-label">CAPABILITIES</p><h2>Skills</h2><div class="skill-list"><?php foreach ($skills as $skill): ?><span><?php echo escapeHtml($skill['skill_name']); ?></span><?php endforeach; ?></div></div></section><?php endif; ?>

        <section class="contact-section" id="contact"><div class="container contact-grid"><div class="contact-copy"><p class="section-label">GET IN TOUCH</p><h2>Let's Work Together</h2><p>Have a project or opportunity in mind? Send a message and I will get back to you.</p>
            <?php if ($personalInfo['email'] !== ''): ?><p>Email: <a href="mailto:<?php echo escapeHtml($personalInfo['email']); ?>"><?php echo escapeHtml($personalInfo['email']); ?></a></p><?php endif; ?>
            <?php if ($personalInfo['phone_primary'] !== ''): ?><p>Phone: <?php echo escapeHtml($personalInfo['phone_primary']); ?></p><?php endif; ?>
            <?php if ($personalInfo['phone_secondary'] !== ''): ?><p>Secondary phone: <?php echo escapeHtml($personalInfo['phone_secondary']); ?></p><?php endif; ?>
            <div class="social-links">
                <?php foreach (['linkedin_url' => 'LinkedIn', 'github_url' => 'GitHub', 'instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'website_url' => 'Website'] as $field => $label): ?>
                    <?php if ($personalInfo[$field] !== ''): ?><a href="<?php echo escapeHtml($personalInfo[$field]); ?>" target="_blank" rel="noopener noreferrer"><?php echo $label; ?></a><?php endif; ?>
                <?php endforeach; ?>
            </div></div><form class="contact-form" method="POST" action="index.php"><h3>Send a Message</h3>
            <?php if ($formSuccess !== ''): ?>
                <div class="form-alert success" role="status"><strong>Message sent successfully</strong><span>Thank you for reaching out. Your message was received.</span><a href="#contact">Send another message</a></div></form>
            <?php else: ?>
            <?php if ($formErrors !== []): ?>
                <ul class="form-alert error" role="alert">
                    <?php foreach ($formErrors as $error): ?>
                        <li><?php echo escapeHtml($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo escapeHtml($name); ?>" maxlength="<?php echo CONTACT_NAME_MAX_LENGTH; ?>" required autocomplete="name">

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo escapeHtml($email); ?>" maxlength="<?php echo CONTACT_EMAIL_MAX_LENGTH; ?>" required autocomplete="email">

                <label for="message">Message</label>
                <textarea id="message" name="message" maxlength="<?php echo CONTACT_MESSAGE_MAX_LENGTH; ?>" required><?php echo escapeHtml($message); ?></textarea>

                <button class="btn btn-primary" type="submit"><span>Send Message</span><span class="button-loading" aria-hidden="true">Sending…</span></button></form>
            <?php endif; ?></div></section>

        <section class="projects-section" id="projects"><div class="container"><p class="section-label">SELECTED WORK</p><h2>Projects</h2><p class="section-intro">A collection of projects that demonstrate experience in technology and problem solving.</p>
            <?php if ($databaseError !== ''): ?>
                <p><?php echo escapeHtml($databaseError); ?></p>
            <?php endif; ?>
            <?php if ($projects === [] && $databaseError === ''): ?><div class="empty-state"><strong>No projects yet</strong><span>New work will appear here soon.</span></div><?php endif; ?>
            <div class="project-grid"><?php foreach ($projects as $project): ?><div class="project-card"><?php displayProjectCard($project); ?></div><?php endforeach; ?></div>
            <div class="json-loader"><button class="btn btn-secondary" type="button" id="load-projects">Load latest projects</button><span id="json-status" role="status">Refresh the project list without leaving the page.</span></div><div class="project-grid" id="json-projects" aria-live="polite"></div></div></section>
        <a href="#home" class="scroll-up" aria-label="Scroll to top">↑</a>
        <script src="script.js?v=2"></script>
    </main>

    <footer class="site-footer"><div class="container footer-grid"><div><strong><?php echo escapeHtml($personalInfo['full_name']); ?></strong><span><?php echo escapeHtml($personalInfo['professional_title']); ?></span></div><div class="social-links"><?php foreach (['github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn', 'website_url' => 'Website'] as $field => $label): ?><?php if ($personalInfo[$field] !== ''): ?><a href="<?php echo escapeHtml($personalInfo[$field]); ?>" target="_blank" rel="noopener noreferrer"><?php echo $label; ?></a><?php endif; ?><?php endforeach; ?></div></div><div class="container footer-bottom"><span>&copy; <?php echo $footerYear; ?> Portfolio. All rights reserved.</span><span class="local-time">Local time: <span id="current-date"></span></span></div>
    </footer>

</body>
</html>
