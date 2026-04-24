<?php
/**
 * FAQ Page
 */
$pageTitle = $pageTitle ?? 'FAQ';
?>
<?php include VIEWS_PATH_TRIMMED . '/partials/head.php'; ?>
<?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>
    <div class="static-container mt-4 about-page">
	    <header class="mb-5">
			<h2 class="display-4"><?php echo htmlspecialchars($pageTitle); ?></h2>
			<p class="lead">
			</p>
		</header>
        <section class="mb-5">
            <h3>What does CORE stand for?</h3>
            <p><strong>C</strong>ommunity-driven <strong>O</strong>pen <strong>R</strong>esearch <strong>E</strong>cosystem.</p>
            <hr>
            <h3>What is the meaning behind the OpenArxiv (CORE) logo <img src="/favicon.svg" alt="CORE" width="32px" height="32px" />?</h3>
			<p>
				The OpenArxiv logo is a visual representation of the Community-driven Open Research Ecosystem. 
				Every element was deliberately chosen to reflect our mission and the academic process:
			</p>
			<ul>
				<li>
					<strong>The Inner 'C' Shape:</strong> 
					Stands for the "Community-driven" foundation of CORE.
				</li>
				<li>
					<strong>The Long Arc with a Dot:</strong> 
					Represents the feedback system of the open research ecosystem. It illustrates the often indirect and long path from research results to their real-world impacts, with the dot symbolizing society and funding agencies.
				</li>
				<li>
					<strong>The Small Arc with a Dot:</strong> 
					Symbolizes researchers making solid, yet difficult, progress in each step of their pursuit of innovation.
				</li>
				<li>
					<strong>The Solid Check Mark:</strong> 
					Represents the rigorous evaluation system that completes the academic feedback loop.
				</li>
				<li>
					<strong>The Outer Thin Circle:</strong> 
					Represents the ever-expanding horizon of discovery and innovation.
				</li>
				<li>
					<strong>Academic Teal:</strong> 
					The primary color evokes trust, knowledge, and scientific rigor.
				</li>
			</ul>
			<hr>
			<h3>What is the significance of the double-shadowed 'E' in the OpenArxiv (CORE) title?</h3>
            <p>
                The <strong> Triple 'E'</strong> design using the double shadow of the 'E' in "CORE" creates a distinct, three-layered look. These layers stand for the three pillars of the platform: <strong>ePrint</strong>, <strong>evaluation</strong>, and <strong>ecosystem</strong>.
            </p>
            <hr>
			<h3>What is the "Rule of 875%" regarding author contributions?</h3>
			<p>
				The "Rule of 875%" is a specific duty assignment mechanism within the CORE evaluation system designed to fairly balance assigned merit with the need for collaboration. Under this rule, each author is assigned a duty percentage reflecting their contribution and responsibility:
			</p>
			<ul>
				<li>
					<strong>Duty Classifications:</strong> 
					Authors are categorized as 1st-class (100% responsibility and full control of the document), other-classified (20% to 99%), or general-unclassified (fixed at 10% each).
				</li>
				<li>
					<strong>The 875% Cap:</strong> 
					The total combined duty percentages of all <em>classified</em> authors on a single document must not exceed 875%.
				</li>
				<li>
					<strong>Promoting Collaboration:</strong> 
					Because the maximum allowable total is well above 100%, the system actively encourages researchers to collaborate without having to divide and dilute a traditional 100% credit limit.
				</li>
				<li>
					<strong>Preventing Abuse:</strong> 
					The 875% cap mathematically prevents "system gaming." For instance, it ensures that no more than 8 people can claim 1st-class authorship on a single document.
				</li>
				<li>
					<strong>Achievement Scoring:</strong> 
					1st-class authors take full credit for the achievement score of the authored document, while other-classified authors receive prorated scores. General-unclassified authors have their achievement scores capped, meaning no limit is needed on the total number of unclassified authors.
				</li>
			</ul>            
        </section>
    </div>
    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
