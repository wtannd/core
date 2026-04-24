<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\News;

class PageController extends BaseController
{
    private News $newsModel;

    public function __construct()
    {
        parent::__construct();
        $this->newsModel = new News();
    }

    /**
     * Show the About Us page
     * GET /about
     */
    public function about(): void
    {
        $this->render('pages/about.php', [
            'pageTitle' => 'About ' . SITE_TITLE
        ]);
    }

    /**
     * Show the Contact page
     * GET /contact
     */
    public function contact(): void
    {
        $this->render('pages/contact.php');
    }

    /**
     * Handle the Contact form submission
     * POST /contact
     */
    public function submitContact(): void
    {
        $this->validateCsrf($_POST);
        if (empty($_POST['email']) || empty($_POST['name']) || empty($_POST['subject']) || empty($_POST['message'])) {
            $_SESSION['error_message'] = "Missing some required information.";
            $this->render('pages/contact.php');
            exit;
        }

        $email = $_POST['email'];
        $subject = $_POST['subject'] . " from " . $_POST['name'];
        $body = $_POST['message'];
        $headers = ['From' => $email, 'Reply-To' => $email, 'X-Mailer' => 'PHP/' . phpversion()];
        mail(SITE_EMAIL, $subject, $body, $headers);
        
        // Redirect with a success message
        $_SESSION['success_message'] = "Thanks for reaching out!";
        header('Location: /contact');
        exit;
    }

    /**
     * Show the FAQ page
     * GET /faq
     */
    public function faq(): void
    {
        $this->render('pages/faq.php', [
            'pageTitle' => 'FAQ'
        ]);
    }

    /**
     * Show the Announcement page
     * GET /announcements
     */
    public function news(): void
    {
        $mRole = (int)($_SESSION['mrole'] ?? GUEST_ROLE);
        $adminRole = (int)($_SESSION['admin_role'] ?? 0);
        $newsList = $this->newsModel->getNewsFeed($mRole, $adminRole);
        $this->render('pages/news.php', [
            'pageTitle' => 'Current Announcements', 'newsList' => $newsList
        ]);
    }

    /**
     * Dynamically load a static page by its slug
     * GET /p/{slug}  (e.g., /p/terms, /p/privacy)
     */
    public function showPage(string $slug): void
    {
        // Sanitize the slug to prevent directory traversal attacks!
        $safeSlug = preg_replace('/[^a-zA-Z0-9-]/', '', $slug);
        
        $viewPath = 'pages/' . $safeSlug . '.php';

        // Check if the physical view file exists
        if (file_exists(VIEWS_PATH_TRIMMED . '/' . $viewPath)) {
            // Convert slug to a readable title (e.g., "privacy-policy" -> "Privacy Policy")
            $title = ucwords(str_replace('-', ' ', $safeSlug));
            
            $this->render($viewPath, [
                'pageTitle' => $title
            ]);
        } else {
            // Page not found, show a 404
            http_response_code(404);
            $this->render('errors/404.php');
        }
    }
}
