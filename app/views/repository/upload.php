<?php
/**
 * Document Upload View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Upload Document</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container doc-container">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php
                // Set defaults for upload mode
                $mode = $mode ?? 'upload';
                $dID = $dID ?? 0;
                $actionUrl = $actionUrl ?? '/upload';
                $cancelUrl = $cancelUrl ?? '/';
                $submitLabel = $submitLabel ?? 'Submit Document';
                $docData = $docData ?? null;
                $isRevise = ($mode === 'revise_doc');
                $isEditDraft = ($mode === 'edit_draft');

                // Pre-populate values from docData or $_POST
                $valTitle = $_POST['title'] ?? ($docData['title'] ?? '');
                $valAbstract = $_POST['abstract'] ?? ($docData['abstract'] ?? '');
                $valNotes = $_POST['notes'] ?? ($docData['notes'] ?? '');
                $valFullText = $_POST['full_text'] ?? ($docData['full_text'] ?? '');
                $valDtype = $_POST['dtype'] ?? ($docData['dtype'] ?? 1);
                $valTID = $_POST['tID'] ?? ($docData['tID'] ?? 0);
                $valIsOld = $_POST['is_old'] ?? ($docData && !empty($docData['pubdate']) ? '1' : '0');
                $valMainPages = $_POST['main_pages'] ?? ($docData['main_pages'] ?? '');
                $valMainFigs = $_POST['main_figs'] ?? ($docData['main_figs'] ?? '');
                $valMainTabs = $_POST['main_tabs'] ?? ($docData['main_tabs'] ?? '');

                // JS data for pre-populating dynamic rows
                $jsAuthorList = $docData ? json_encode($docData['author_list'] ?? []) : 'null';
                $jsBranches = $docData ? json_encode($docData['branches'] ?? []) : 'null';
                $jsExtLinks = $docData ? json_encode($docData['ext_links'] ?? []) : 'null';
            ?>

            <form action="<?php echo $actionUrl; ?>" method="POST" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="author_list_json" id="author_list_json">
                <input type="hidden" name="link_list_json" id="link_list_json">
                <input type="hidden" name="branch_list_json" id="branch_list_json">
                <input type="hidden" name="form_mode" value="<?php echo $mode; ?>">
                <?php if ($dID > 0): ?>
                <input type="hidden" name="dID" value="<?php echo $dID; ?>">
                <?php endif; ?>

                <?php if (!$isRevise): ?>
                <div class="form-group">
                    <div class="submission-type-toggle">
                        <button type="button" class="submission-type-btn <?php echo $valIsOld === '0' ? 'active' : ''; ?>" data-value="0" onclick="toggleSubmissionType(this)">New Submission</button>
                        <button type="button" class="submission-type-btn <?php echo $valIsOld === '1' ? 'active' : ''; ?>" data-value="1" onclick="toggleSubmissionType(this)">Published/Old Document</button>
                    </div>
                    <input type="hidden" name="is_old" id="is_old" value="<?php echo htmlspecialchars($valIsOld); ?>">
                </div>
                <?php endif; ?>

                <div class="form-group" id="old-date-group" style="display: none;">
                    <div class="date-row">
                        <div>
                            <label>Date Published in Journal or Posted as ePrint:</label>
                            <div class="date-inline-group">
                                <input type="number" name="pub_year" id="pub_year" min="1000" max="9999" placeholder="YYYY" value="<?php echo htmlspecialchars($_POST['pub_year'] ?? ''); ?>">
                                <span>/</span>
                                <input type="number" name="pub_month" id="pub_month" min="1" max="12" placeholder="MM" class="short" value="<?php echo htmlspecialchars($_POST['pub_month'] ?? ''); ?>">
                                <span>/</span>
                                <input type="number" name="pub_day" id="pub_day" min="1" max="31" placeholder="DD" class="short" value="<?php echo htmlspecialchars($_POST['pub_day'] ?? ''); ?>">
                            </div>
                            <small>Year required. Month/day optional.</small>
                        </div>
                        <div>
                            <label>Date Received in Journal (Optional):</label>
                            <div class="date-inline-group">
                                <input type="number" name="recv_year" id="recv_year" min="1000" max="9999" placeholder="YYYY" value="<?php echo htmlspecialchars($_POST['recv_year'] ?? ''); ?>">
                                <span>/</span>
                                <input type="number" name="recv_month" id="recv_month" min="1" max="12" placeholder="MM" class="short" value="<?php echo htmlspecialchars($_POST['recv_month'] ?? ''); ?>">
                                <span>/</span>
                                <input type="number" name="recv_day" id="recv_day" min="1" max="31" placeholder="DD" class="short" value="<?php echo htmlspecialchars($_POST['recv_day'] ?? ''); ?>">
                            </div>
                            <small>If omitted, Date Published is used.</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dtype"><h3>Document Type:</h3></label>
                    <select name="dtype" id="dtype" class="form-control" required>
                        <?php foreach ($docTypes as $type): ?>
                            <option value="<?= $type['ID'] ?>" <?= (int)$type['ID'] === (int)$valDtype ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['dtname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title"><h3>Document Title:</h3></label>
                    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($valTitle); ?>">
                </div>

                <div class="form-group">
                    <label for="abstract"><h3>Abstract:</h3></label>
                    <textarea id="abstract" name="abstract" rows="6" required><?php echo htmlspecialchars($valAbstract); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="notes"><h3>Notes:</h3></label>
                    <input type="text" id="notes" name="notes" value="<?php echo htmlspecialchars($valNotes); ?>">
                </div>

                <hr>

                <h3>Affiliations</h3>
                <div id="affiliations-container">
                    <!-- Dynamic affiliation rows -->
                </div>
                <button type="button" class="btn btn-add" onclick="addAffiliationRow()">+ Add Affiliation</button>

                <hr>

                <h3>Authors &amp; Contributions</h3>
                <div class="duty-rules-box">
                    <strong>Duty Assignment Rules:</strong> Each author is assigned a duty percentage reflecting their contribution/responsibility level.
                    <ul>
                        <li><strong>1st-class:</strong> 100% &nbsp;|&nbsp; <strong>other-classified:</strong> 20% &ndash; 99% &nbsp;|&nbsp; <strong>general-unclassified:</strong> 10% each</li>
                        <li>1st-class authors have full responsibility and control of the document</li>
                        <li>The total of all classified duties must not exceed <strong>875%</strong>.</li>
                    </ul>
                </div>

                <div class="batch-add-row">
                    <textarea id="batch-core-ids" rows="1" class="form-control" placeholder="CORE-IDs (e.g., 12A-45B-78C, 65B32A)"></textarea>
                    <input type="text" id="batch-aff-ids" placeholder="Aff. IDs (e.g. 1,2)">
                    <button type="button" id="btn-lookup-authors" class="btn btn-add">Lookup &amp; Add</button>
                </div>

                <div id="authors-container">
                    <!-- Rows generated by JS -->
                </div>

                <div class="author-add-bar">
                    <input type="number" id="manual-insert-pos" min="1" placeholder="Pos." class="author-pos-input">
                    <button type="button" id="btn-add-manual" class="btn btn-secondary">+ Add Manually</button>
                    <button type="button" id="btn-add-myself" class="btn btn-secondary">+ Add Myself</button>
                </div>
                <div class="duty-summary" id="duty-summary">Total Duty: 0%</div>

                <hr>

                <h3>Research Branches</h3>
                <p class="form-hint">Assign 1 to 3 research branches. The total impact must equal 100%.</p>
                <div id="branches-container">
                    <!-- Dynamic branch rows -->
                </div>
                <div class="branch-controls">
                    <button type="button" class="btn btn-add" id="btn-add-branch" onclick="addBranchRow()">+ Add Branch</button>
                    <span class="branch-summary" id="branch-summary">Total Impact: 100%</span>
                </div>

                <hr>

                <div class="form-group">
                    <label for="tID"><h3>Research Topic (optional):</h3></label>
                    <select name="tID" id="tID" class="form-control">
                        <option value="0">-- None --</option>
                        <?php foreach ($researchTopics as $t): ?>
                            <option value="<?php echo $t['tID']; ?>" <?php echo (int)$t['tID'] === (int)$valTID ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['abbr'] . ' — ' . $t['tname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr>

                <h3>External Links</h3>
                <div id="links-container">
                    <!-- Dynamic link rows -->
                </div>
                <button type="button" class="btn btn-add" onclick="addLinkRow()">+ Add Link</button>

                <hr>

                <div class="file-section">
                    <h3>Attach Files</h3>
                    <?php if ($isEditDraft || $isRevise): ?>
                    <div class="form-hint">
                        <?php
                        if ($isEditDraft) {
                            $hasMain = ($docData['has_file'] ?? 0) >= 1;
                            $supplType = ($docData['has_file'] ?? 0) === 2 ? 'PDF' : (($docData['has_file'] ?? 0) === 3 ? 'ZIP' : null);
                        } else {
                            $hasMain = ($docData['version'] ?? 0) >= 1;
                            $supplType = ($docData['ver_suppl'] ?? 0) >= 1 ? (($docData['suppl_ext'] ?? 0) === 2 ? 'ZIP' : 'PDF') : null;
                        }
                        ?>
                        Current file: <strong><?php echo $hasMain ? 'Main PDF attached' : 'None'; ?>
                        <?php if ($supplType): ?> + Supplemental <?php echo $supplType; ?><?php endif; ?></strong>
                        — upload new files below to replace.
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="main_file">Main Document (PDF):</label>
                        <input type="file" id="main_file" name="main_file" accept=".pdf" onchange="toggleFullText()">
                        
                        <div class="metrics-group">
                            <div>
                                <label for="main_pages">Pages (Main):</label>
                                <input type="number" name="main_pages" id="main_pages" min="1" class="form-control" value="<?php echo htmlspecialchars((string)$valMainPages); ?>">
                            </div>
                            <div>
                                <label for="main_figs">Figures:</label>
                                <input type="number" name="main_figs" id="main_figs" min="0" class="form-control" value="<?php echo htmlspecialchars((string)$valMainFigs); ?>">
                            </div>
                            <div>
                                <label for="main_tabs">Tables:</label>
                                <input type="number" name="main_tabs" id="main_tabs" min="0" class="form-control" value="<?php echo htmlspecialchars((string)$valMainTabs); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="supplemental_file">Supplemental File (PDF or ZIP):</label>
                        <input type="file" id="supplemental_file" name="supplemental_file" accept=".pdf,.zip" onchange="toggleFullText()">
                    </div>
                </div>

                <?php if ($isRevise): ?>
                <div class="form-group">
                    <label for="revision_notes"><h3>Revision Notes:</h3></label>
                    <textarea id="revision_notes" name="revision_notes" rows="3" placeholder="Describe what changed in this revision..."><?php echo htmlspecialchars($_POST['revision_notes'] ?? ''); ?></textarea>
                </div>
                <?php endif; ?>

                <div class="form-group" id="full-text-group" style="display: none;">
                    <label for="full_text">Full Text (if no file attached):</label>
                    <textarea id="full_text" name="full_text" rows="8"><?php echo htmlspecialchars($valFullText); ?></textarea>
                </div>

                <div class="submit-group">
                    <?php if ($mode === 'upload'): ?>
                        <button type="submit" name="action" value="draft" class="btn btn-draft">Save as Draft</button>
                        <button type="submit" name="action" value="submit" class="btn btn-submit" onclick="return validateDuty()">Submit Document</button>
                    <?php elseif ($mode === 'edit_draft'): ?>
                        <button type="submit" name="action" value="draft" class="btn btn-draft">Update Draft</button>
                        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancel</a>
                    <?php elseif ($mode === 'revise_doc'): ?>
                        <button type="submit" name="action" value="update" class="btn btn-submit">Update Document</button>
                        <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>

    <script>
        const MAX_UPLOAD_SIZE = <?php echo defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 10485760; ?>;
        const AVAILABLE_SOURCES = <?php echo json_encode($availableSources ?? []); ?>;
        const USER_DATA = {
            mID: <?php echo $_SESSION['mID'] ?? 'null'; ?>,
            display_name: <?php echo json_encode($_SESSION['display_name'] ?? ''); ?>,
            pub_name: <?php echo json_encode($_SESSION['pub_name'] ?? ''); ?>,
            core_id: <?php echo json_encode($_SESSION['core_id'] ?? ''); ?>
        };

        function validateFileSize(event) {
            const file = event.target.files[0];
            if (file && file.size > MAX_UPLOAD_SIZE) {
                alert(`This file exceeds the maximum allowed size of ${MAX_UPLOAD_SIZE / (1024 * 1024)} MB.`);
                event.target.value = '';
            }
            toggleFullText();
        }

        document.getElementById('main_file').addEventListener('change', validateFileSize);
        document.getElementById('supplemental_file').addEventListener('change', validateFileSize);

        function toggleSubmissionType(btn) {
            const val = btn.dataset.value;
            document.getElementById('is_old').value = val;
            btn.closest('.submission-type-toggle').querySelectorAll('.submission-type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const isOld = val === '1';
            document.getElementById('old-date-group').style.display = isOld ? 'block' : 'none';
            document.getElementById('pub_year').required = isOld;
        }

        function toggleOldDate() {
            const isOld = document.getElementById('is_old').value === '1';
            document.getElementById('old-date-group').style.display = isOld ? 'block' : 'none';
            document.getElementById('pub_year').required = isOld;
        }

        function toggleFullText() {
            const mainFile = document.getElementById('main_file').files.length;
            const supplFile = document.getElementById('supplemental_file').files.length;
            const showFullText = (mainFile === 0 && supplFile === 0);
            document.getElementById('full-text-group').style.display = showFullText ? 'block' : 'none';
        }

        // Initialize visibility
        toggleFullText();

        // --- Authors Logic ---
        const authorsContainer = document.getElementById('authors-container');

        function createAuthorRow(author = {pub_name: '', mID: '', core_id: '', is_manual: true}) {
            const row = document.createElement('div');
            row.className = 'author-row';

            row.innerHTML = `
                <div class="move-btns">
                    <button type="button" class="btn-up">↑</button>
                    <button type="button" class="btn-down">↓</button>
                </div>
                <input type="text" class="auth-pub-name" placeholder="Publication Name" required value="${author.pub_name}" ${author.is_manual ? '' : 'readonly'}>
                <input type="text" class="auth-core-id" placeholder="CORE-ID" value="${author.core_id}" readonly ${author.is_manual ? 'disabled' : ''}>
                <input type="hidden" class="auth-mid" value="${author.mID}">
                <input type="number" class="auth-duty duty-input" placeholder="Duty" required min="10" max="100" value="100">
                <input type="text" class="auth-aff-refs" placeholder="Aff. IDs" value="1">
                <button type="button" class="btn-remove remove-author">X</button>
            `;

            row.querySelector('.duty-input').addEventListener('change', enforceDutyRules);
            return row;
        }

        function autoDistributeDuties() {
            let total = 0;
            const inputs = document.querySelectorAll('.duty-input');
            inputs.forEach(input => {
                if (total + 100 <= 875) {
                    input.value = 100;
                    total += 100;
                } else if (total < 875) {
                    input.value = 875 - total;
                    total = 875;
                } else {
                    input.value = 10;
                }
            });
            updateDutySummary();
        }

        function enforceDutyRules(e) {
            let val = parseInt(e.target.value) || 10;
            if (val !== 10) {
                val = Math.max(20, Math.min(100, val));
            }

            let sumOfGenerals = 0;
            document.querySelectorAll('.duty-input').forEach(input => {
                if (input !== e.target && parseInt(input.value) >= 20) {
                    sumOfGenerals += parseInt(input.value);
                }
            });

            if (val >= 20 && (sumOfGenerals + val) > 875) {
                val = 875 - sumOfGenerals;
                if (val < 20) val = 10;
            }

            e.target.value = val;
            updateDutySummary();
        }

        // Reordering logic
        authorsContainer.addEventListener('click', (e) => {
            const row = e.target.closest('.author-row');
            if (!row) return;

            if (e.target.classList.contains('btn-up')) {
                const prev = row.previousElementSibling;
                if (prev) authorsContainer.insertBefore(row, prev);
            } else if (e.target.classList.contains('btn-down')) {
                const next = row.nextElementSibling;
                if (next) authorsContainer.insertBefore(next, row);
            } else if (e.target.classList.contains('btn-remove')) {
                row.remove();
                updateDutySummary();
            }
        });

        // Batch lookup
		document.getElementById('btn-lookup-authors').addEventListener('click', function() {
			const textarea = document.getElementById('batch-core-ids');
			const csrfToken = document.querySelector('input[name="csrf_token"]').value;

			// Optional: Visual feedback that it is loading
			const btn = this;
			const originalText = btn.innerHTML;
			btn.innerHTML = 'Loading...';
			btn.disabled = true;

			fetch('/lookupAuthors', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				// We include the CSRF token in the JSON body just in case your backend requires it
				body: JSON.stringify({ 
					text: textarea.value,
					csrf_token: csrfToken 
				})
			})
			.then(response => {
				// If the server returns a 404 or 500, throw an error to trigger the catch block
				if (!response.ok) {
					throw new Error(`HTTP Error status: ${response.status}`);
				}
				return response.text(); // Read as text first to prevent silent JSON parse crashes
			})
			.then(text => {
				try {
					const data = JSON.parse(text); // Try parsing it
					
					if (data.length === 0) {
						alert("No members found matching those IDs.");
					} else {
						const members = data;
						const batchAffIds = document.getElementById('batch-aff-ids').value.trim();
						members.forEach(m => {
							const row = createAuthorRow({
								pub_name: m.pub_name,
								mID: m.mID,
								core_id: m.core_id,
								is_manual: false
							});
							if (batchAffIds) {
								row.querySelector('.auth-aff-refs').value = batchAffIds;
							}
							document.getElementById('authors-container').appendChild(row);
						});
						
						// Recalculate duties after adding
						if (typeof autoDistributeDuties === 'function') {
							autoDistributeDuties();
						}
						
						textarea.value = ''; // Clear box on success
						document.getElementById('batch-aff-ids').value = '';
					}
				} catch (e) {
					console.error("Failed to parse JSON. Server returned:", text);
					alert("Server returned an invalid response. Check the browser console.");
				}
			})
			.catch(error => {
				console.error("Fetch failed:", error);
				alert("A network or server error occurred. Check the browser console.");
			})
			.finally(() => {
				// Restore button state
				btn.innerHTML = originalText;
				btn.disabled = false;
			});
		});

        function insertAuthorAtPosition(row) {
            const posInput = document.getElementById('manual-insert-pos');
            const pos = parseInt(posInput.value);
            const children = authorsContainer.children;

            if (!isNaN(pos) && pos >= 1 && pos <= children.length + 1) {
                const target = children[pos - 1];
                authorsContainer.insertBefore(row, target);
            } else {
                authorsContainer.appendChild(row);
            }
            posInput.value = '';
            autoDistributeDuties();
        }

        // Manual Add
        document.getElementById('btn-add-manual').onclick = () => {
            const row = createAuthorRow();
            insertAuthorAtPosition(row);
        };

        // Add Myself as Author
        document.getElementById('btn-add-myself').onclick = () => {
            if (!USER_DATA.mID) {
                alert('You must be logged in to add yourself as an author.');
                return;
            }

            // Check if already added
            const existingMids = document.querySelectorAll('.auth-mid');
            for (const input of existingMids) {
                if (parseInt(input.value) === USER_DATA.mID) {
                    alert('You are already in the author list.');
                    return;
                }
            }

            const row = createAuthorRow({
                pub_name: USER_DATA.pub_name,
                mID: USER_DATA.mID,
                core_id: USER_DATA.core_id,
                is_manual: false
            });
            insertAuthorAtPosition(row);
        };

        // Pre-populated data for edit/revise modes
        const EDIT_AUTHORS = <?php echo $jsAuthorList; ?>;
        const EDIT_BRANCHES = <?php echo $jsBranches; ?>;
        const EDIT_LINKS = <?php echo $jsExtLinks; ?>;

        // Initialize with Submitter or pre-populated data
        document.addEventListener('DOMContentLoaded', () => {
            // Authors
            if (EDIT_AUTHORS && EDIT_AUTHORS.authors && EDIT_AUTHORS.authors.length > 0) {
                // Pre-populate affiliations
                const affContainer = document.getElementById('affiliations-container');
                if (EDIT_AUTHORS.affiliations) {
                    EDIT_AUTHORS.affiliations.forEach(aff => {
                        addAffiliationRow(aff[1]);
                    });
                }
                // Pre-populate authors
                EDIT_AUTHORS.authors.forEach(a => {
                    const row = createAuthorRow({
                        pub_name: a[0] || '',
                        mID: a[1] || '',
                        core_id: '',
                        is_manual: !a[1]
                    });
                    // Set duty and aff-refs
                    row.querySelector('.auth-duty').value = a[2] || 100;
                    if (a[3] && a[3].length > 0) {
                        row.querySelector('.auth-aff-refs').value = a[3].join(',');
                    }
                    authorsContainer.appendChild(row);
                });
                updateDutySummary();
            } else if (USER_DATA.mID) {
                const row = createAuthorRow({
                    pub_name: USER_DATA.display_name,
                    mID: USER_DATA.mID,
                    core_id: USER_DATA.core_id,
                    is_manual: false
                });
                authorsContainer.appendChild(row);
                autoDistributeDuties();
            }

            // Branches
            if (EDIT_BRANCHES && EDIT_BRANCHES.length > 0) {
                branchesContainer.innerHTML = '';
                EDIT_BRANCHES.forEach(b => {
                    branchesContainer.appendChild(createBranchRow(b.bID, b.impact));
                });
                renumberBranches();
                updateBranchSummary();
            }

            // Links
            if (EDIT_LINKS && EDIT_LINKS.length > 0) {
                EDIT_LINKS.forEach(l => {
                    addLinkRow(l[0] || 0, l[2] || '', l[1] || '');
                });
            }

            // Show old-date group if needed
            const isOldHidden = document.getElementById('is_old');
            if (isOldHidden && isOldHidden.value === '1') {
                document.getElementById('old-date-group').style.display = 'block';
                document.getElementById('pub_year').required = true;
            }
        });

        // --- Affiliations & Links --- (Maintain existing helper functions)
        function addAffiliationRow(name = '') {
            const container = document.getElementById('affiliations-container');
            const index = container.children.length + 1;
            const row = document.createElement('div');
            row.className = 'author-row affiliation-row';
            row.innerHTML = `
                <span>${index}.</span>
                <input type="text" class="aff-name" placeholder="Affiliation Name" required value="${name}">
                <button type="button" class="remove-author" onclick="this.parentElement.remove(); reindexAffiliations();">Remove</button>
            `;
            container.appendChild(row);
        }

        function reindexAffiliations() {
            const container = document.getElementById('affiliations-container');
            Array.from(container.children).forEach((row, idx) => {
                row.querySelector('span').textContent = `${idx + 1}.`;
            });
        }

        function addLinkRow(sid = '', url = '', esname = '') {
            // Prefix bare DOIs for display
            if (url && url.startsWith('10.')) {
                url = 'https://doi.org/' + url;
            }
            const container = document.getElementById('links-container');
            const row = document.createElement('div');
            row.className = 'author-row link-row';
            
            let options = '<option value="">Select Source</option>';
            AVAILABLE_SOURCES.forEach(src => {
                options += `<option value="${src.sID}" ${src.sID == sid ? 'selected' : ''}>${src.esname}</option>`;
            });

            const selectedSrc = AVAILABLE_SOURCES.find(src => src.sID == sid);
            const isCustom = !sid || sid <= 2;
            const displayEsname = isCustom ? esname : (selectedSrc ? selectedSrc.esname : '');

            row.innerHTML = `
                <select class="link-sid" required onchange="toggleLinkName(this)">${options}</select>
                <input type="text" class="link-esname" placeholder="Source Name" value="${displayEsname}" ${isCustom ? 'required' : 'readonly'}>
                <input type="url" class="link-url" placeholder="URL or DOI" required value="${url}">
                <button type="button" class="remove-author" onclick="this.parentElement.remove();">Remove</button>
            `;
            container.appendChild(row);
        }

        function toggleLinkName(selectEl) {
            const row = selectEl.closest('.author-row');
            const nameInput = row.querySelector('.link-esname');
            const sid = parseInt(selectEl.value) || 0;
            const src = AVAILABLE_SOURCES.find(s => s.sID == sid);

            if (sid > 2 && src) {
                nameInput.value = src.esname;
                nameInput.readOnly = true;
                nameInput.required = false;
            } else {
                nameInput.value = '';
                nameInput.readOnly = false;
                nameInput.required = true;
            }
        }

        function updateDutySummary() {
            const inputs = document.querySelectorAll('.duty-input');
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            document.getElementById('duty-summary').textContent = `Total Duty: ${total}%`;
            document.getElementById('duty-summary').className = total > 875 ? 'duty-summary text-danger' : 'duty-summary';
        }

        function validateDuty() {
            const inputs = document.querySelectorAll('.duty-input');
            let total = 0;
            inputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });

            if (total > 875) {
                alert('Total duty cannot exceed 875%.');
                return false;
            }
            return true;
        }

        // --- Research Branches Logic ---
        const BRANCHES_DATA = <?php echo json_encode($researchBranches ?? []); ?>;
        const branchesContainer = document.getElementById('branches-container');

        function createBranchRow(bID = '', impact = 100) {
            const num = branchesContainer.children.length + 1;
            const row = document.createElement('div');
            row.className = 'branch-row';

            let options = '<option value="">-- Select Branch --</option>';
            BRANCHES_DATA.forEach(b => {
                const label = b.abbr + ' — ' + b.bname;
                options += `<option value="${b.bID}" ${b.bID == bID ? 'selected' : ''}>${label}</option>`;
            });

            row.innerHTML = `
                <span class="branch-num">${num}.</span>
                <select class="branch-bid" required>${options}</select>
                <div class="branch-impact-group">
                    <input type="number" class="branch-impact" min="1" max="100" value="${impact}" required>
                    <span>%</span>
                </div>
                <button type="button" class="remove-author" onclick="removeBranchRow(this);">X</button>
            `;

            row.querySelector('.branch-impact').addEventListener('change', updateBranchSummary);
            return row;
        }

        function addBranchRow() {
            const count = branchesContainer.children.length;
            if (count >= <?php echo DOC_BRANCH_MAX; ?>) {
                alert('Maximum of <?php echo DOC_BRANCH_MAX; ?> branches allowed.');
                return;
            }
            // Redistribute: for 1 row (100) -> add new, split 50/50
            // For 2 rows (e.g., 50/50) -> add new, split 34/33/33
            const row = createBranchRow();
            branchesContainer.appendChild(row);
            rebalanceBranches();
        }

        function removeBranchRow(btn) {
            if (branchesContainer.children.length <= 1) {
                alert('At least one research branch is required.');
                return;
            }
            btn.closest('.branch-row').remove();
            renumberBranches();
            rebalanceBranches();
        }

        function renumberBranches() {
            Array.from(branchesContainer.children).forEach((row, idx) => {
                row.querySelector('.branch-num').textContent = (idx + 1) + '.';
            });
        }

        function rebalanceBranches() {
            const rows = Array.from(branchesContainer.children);
            const count = rows.length;
            if (count === 0) return;

            // Distribute impact evenly: 100 / count, remainder to first
            const base = Math.floor(100 / count);
            const remainder = 100 - base * count;

            rows.forEach((row, idx) => {
                const input = row.querySelector('.branch-impact');
                input.value = idx === 0 ? base + remainder : base;
            });

            updateBranchSummary();

            // Show/hide add button
            document.getElementById('btn-add-branch').style.display = count >= <?php echo DOC_BRANCH_MAX; ?> ? 'none' : '';
        }

        function updateBranchSummary() {
            let total = 0;
            document.querySelectorAll('.branch-impact').forEach(input => {
                total += parseInt(input.value) || 0;
            });
            const summary = document.getElementById('branch-summary');
            summary.textContent = `Total Impact: ${total}%`;
            summary.className = total !== 100 ? 'branch-summary text-danger' : 'branch-summary';
        }

        function collectBranchJson() {
            const rows = Array.from(branchesContainer.children);
            const branches = [];
            rows.forEach((row, idx) => {
                const bID = parseInt(row.querySelector('.branch-bid').value) || 0;
                const impact = parseInt(row.querySelector('.branch-impact').value) || 0;
                if (bID > 0) {
                    branches.push({ bID: bID, num: idx + 1, impact: impact });
                }
            });
            return JSON.stringify(branches);
        }

        // Initialize with one branch row
        document.addEventListener('DOMContentLoaded', () => {
            if (branchesContainer.children.length === 0) {
                branchesContainer.appendChild(createBranchRow());
            }
        });

        document.getElementById('upload-form').onsubmit = function(e) {
            // Validate branches
            let branchTotal = 0;
            let branchCount = 0;
            const branchRows = document.querySelectorAll('#branches-container .branch-row');
            const selectedBids = new Set();
            branchRows.forEach(row => {
                const bID = parseInt(row.querySelector('.branch-bid').value) || 0;
                const impact = parseInt(row.querySelector('.branch-impact').value) || 0;
                if (bID > 0) {
                    branchCount++;
                    branchTotal += impact;
                    if (selectedBids.has(bID)) {
                        e.preventDefault();
                        alert('Each research branch must be unique.');
                        return false;
                    }
                    selectedBids.add(bID);
                }
            });
            if (branchCount < 1) {
                e.preventDefault();
                alert('Please select at least one research branch.');
                return false;
            }
            if (branchTotal !== 100) {
                e.preventDefault();
                alert('Branch impact percentages must sum to 100%. Currently: ' + branchTotal + '%');
                return false;
            }

            // Collect branches
            document.getElementById('branch_list_json').value = collectBranchJson();
            // Collect Affiliations
            const affInputs = document.querySelectorAll('#affiliations-container .aff-name');
            const affiliations = [];
            affInputs.forEach((input, idx) => {
                affiliations.push([idx + 1, input.value]);
            });

            // Collect Authors
            const authRows = document.querySelectorAll('#authors-container .author-row');
            const authors = [];
            authRows.forEach(row => {
                const mIDValue = row.querySelector('.auth-mid').value;
                const affRefs = row.querySelector('.auth-aff-refs').value
                    .split(',')
                    .map(s => parseInt(s.trim()))
                    .filter(n => !isNaN(n));

                authors.push([
                    row.querySelector('.auth-pub-name').value,
                    mIDValue ? parseInt(mIDValue) : null,
                    parseInt(row.querySelector('.auth-duty').value) || 0,
                    affRefs
                ]);
            });

            const authorListObj = {
                authors: authors,
                affiliations: affiliations
            };
            document.getElementById('author_list_json').value = JSON.stringify(authorListObj);

            // Collect links into JSON
            const linkRows = document.querySelectorAll('#links-container .author-row');
            const links = [];
            linkRows.forEach(row => {
                links.push([
                    parseInt(row.querySelector('.link-sid').value) || 0,
                    row.querySelector('.link-esname').value,
                    row.querySelector('.link-url').value
                ]);
            });
            document.getElementById('link_list_json').value = JSON.stringify(links);
            
            return true;
        };
    </script>
</body>
</html>
