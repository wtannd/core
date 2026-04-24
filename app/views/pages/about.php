<?php
/**
 * About Page
 */
$pageTitle = $pageTitle ?? 'About';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <div class="static-container mt-4 about-page">    
	    <header class="mb-5">
			<h1 class="display-4"><?php echo htmlspecialchars($pageTitle); ?></h1>
			<p class="lead">
				Welcome to OpenArxiv (CORE)—the <strong>C</strong>ommunity-driven <strong>O</strong>pen <strong>R</strong>esearch <strong>E</strong>cosystem.
			</p>
		</header>

		<section class="mb-5">
			<p>
				OpenArxiv is a live instantiation of CORE, serving as a novel platform for ePrint services backed by rigorous, community-based evaluation of scientific research. Our ultimate goal is to build a healthy, self-sustaining research ecosystem for the modern era.
			</p>
		</section>

		<section class="mb-5">
			<h2>Our Mission: Why CORE?</h2>
			<p>
				We believe that a deep structural crisis in academia currently requires a complete reform to ensure continued scientific innovation. CORE is expressly designed to ensure fairness, transparency, and efficiency in knowledge dissemination, resource allocation, and career advancement. By addressing these foundational issues, we aim to create the optimal conditions for a new era of innovation in basic research.
			</p>
		</section>

		<section class="mb-5">
			<h2>Our Core Principles</h2>
			<p>
				The foundation of OpenArxiv relies on three defining pillars, guided by our overarching <strong>Diversity Principle</strong>, which advocates for a merit-based system that constantly cultivates diverse ideas and directions to foster innovation.
			</p>
			<ul class="list-group list-group-flush mb-3">
				<li class="list-group-item">
					<strong>Community-driven:</strong> We engage the entire community so that participation is all-inclusive, voices are democratic, and diverse research ideas are preserved and actively promoted. This approach mirrors a healthy society with built-in monopoly-breaking measures against the top and a robust safety net for the bottom.
				</li>
				<li class="list-group-item">
					<strong>Open:</strong> Our system is open-ended, open-minded, open-to-risk, and dynamically calibrated. We maintain procedural transparency while fiercely protecting each individual’s will and privacy, constantly evolving to meet new challenges and resist system gaming.
				</li>
				<li class="list-group-item">
					<strong>Research:</strong> We demand a scientific, rigorous, and strictly merit-based ethos in all approaches.
				</li>
				<li class="list-group-item">
					<strong>Ecosystem:</strong> We implement healthy, self-correcting feedback loops and proper incentive mechanisms to maintain a living, self-regulating system.
				</li>
			</ul>
		</section>

		<section class="mb-5">
			<h2>The Rigorous Dual Evaluation System</h2>
			<p>
				To fix the broken academic incentive loop, CORE introduces a <strong>Rigorous Dual Evaluation System</strong> for STEM and other research fields.
			</p>
			<ul>
				<li><strong>Comprehensive Evaluation:</strong> Three primary research activities—original research, indirect contributions (such as peer review), and funding/resource requests—are evaluated using quantitative, continually refined metrics.</li>
				<li><strong>Quality over Quantity:</strong> Our community-based evaluation system explicitly rewards the quality, rather than the raw quantity, of accomplishments, measured via a sophisticated system of Achievement Levels (AL).</li>
				<li><strong>Credit-based Incentives:</strong> Members earn credits known as Earned Credit Points (ECP) and advance in community roles as they accumulate experience and make valuable contributions.</li>
				<li><strong>Fostering Innovation:</strong> Through our sophisticated incentive mechanisms, high-risk, high-reward research gets a significantly better chance of being funded.</li>
			</ul>
		</section>

		<section class="mb-5">
			<h2>The Platform</h2>
			<p>
				OpenArxiv features a clean, academic, and minimalist design built to serve the researcher. From seamless ORCID login integration to a robust ePrint repository supporting math rendering (MathJax for TeX/LaTeX) and multi-parent cross-disciplinary research branches and topics, the platform is tailored for scientific rigorousness.
			</p>
			<p>
				Under the hood, OpenArxiv is built entirely with vanilla PHP 8+, MySQL/MariaDB, and modern MVC techniques without the bloat of heavy frameworks, ensuring maximum security, longevity, and performance. See the active development of this project at <a href="https://github.com/wtannd/core" target="_blank" rel="noopener noreferrer">GitHub</a>.
			</p>
		</section>

		<section class="mb-5">
			<h2>The Future of CORE</h2>
			<p>
				OpenArxiv is actively evolving. In our upcoming phases, we will introduce advanced rating and review workflows, a comprehensive funding allocation system, decentralized committees for governance, and collaboration tools tailored for research teams and sponsoring member organizations.
			</p>
			<p class="font-weight-bold mt-4">
				Join us in building a fairer, more transparent future for scientific discovery.
			</p>
		</section>

		<section class="mb-5">
		    <h2>References:</h2>
			<ol>
			  <li><a href="https://osf.io/preprints/metaarxiv/y9qh6/" class="link-academic" download>A Robust Community-Based Credit System to Enhance Peer Review in Scientific Research</a></li>
			  <li><a href="https://osf.io/preprints/osf/jvkmz_v2" class="link-academic" download>OePRESS: an Open ePrint and Rigorous Evaluation System for STEM</a></li>
			  <li><a href="https://osf.io/preprints/metaarxiv/7crfu" class="link-academic" download>Fostering Innovation Through Sociological Overhaul: A Proposal for a Community-driven Open Research Ecosystem (CORE)</a></li>
			</ol>		    
		</section>
    </div>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
