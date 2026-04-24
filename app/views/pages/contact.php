<?php
/**
 * Contact Page
 */
$pageTitle = $pageTitle ?? 'Contact Us';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <div class="static-container mt-4 about-page">
	    <header class="mb-5">
			<h1 class="display-4"><?php echo htmlspecialchars($pageTitle); ?></h1>
			<p class="lead">
                Have a question, feedback, or need support with the OpenArxiv (CORE) platform? 
                Please use the form below or reach out to the development team at <a href="https://github.com/wtannd/core" target="_blank" rel="noopener noreferrer">GitHub</a>.
			</p>
		</header>
        <div class="contact-form-wrapper">
            <form action="/contact" method="POST" class="contact-form">
                
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group row">
                    <div class="col-half">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="col-half">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="subject">Subject <span class="required">*</span></label>
                    <select id="subject" name="subject" class="form-control" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                        <option value="">-- Select a topic --</option>
                        <option value="General Inquiry">General Inquiry</option>
                        <option value="Technical Support">Technical Support</option>
                        <option value="Feedback / Suggestion">Feedback / Suggestion</option>
                        <option value="Report an Issue">Report an Issue</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message">Message <span class="required">*</span></label>
                    <textarea id="message" name="message" class="form-control" rows="10" required value="<?php echo htmlspecialchars($_POST['message'] ?? ''); ?>"></textarea>
                </div>

                <div class="form-submit">
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
