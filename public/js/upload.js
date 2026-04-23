/**
 * Upload.js - JavaScript for upload.php
 * 
 * This file requires the following global variables to be defined in upload.php:
 * - MAX_UPLOAD_SIZE (number)
 * - AVAILABLE_SOURCES (array)
 * - USER_DATA (object)
 * - EDIT_AUTHORS (object)
 * - EDIT_BRANCHES (array)
 * - EDIT_LINKS (array)
 * - BRANCHES_DATA (array)
 * - DOC_BRANCH_MAX (number)
 */

// Note: MAX_UPLOAD_SIZE, AVAILABLE_SOURCES, USER_DATA are defined in upload.php before this file is included

function toggleBlock(blockId, btn) {
    const block = document.getElementById(blockId);
    if (!block) return;
    const listId = blockId.replace(/^block-(author|branch|link)e?s$/,"$1_list_json");
    const listInput = document.getElementById(listId);

    const body = block.querySelector('.block-body');
    const fieldset = body.querySelector('fieldset');

    // Check if the block is currently disabled
    const isCurrentlyDisabled = fieldset.hasAttribute('disabled');

    if (isCurrentlyDisabled) {
        // Enable it for editing
        fieldset.removeAttribute('disabled');
        body.classList.remove('is-disabled');
        
        // Update button appearance
        btn.textContent = 'Disable';
        btn.classList.add('btn-is-editing');

        if (listInput) listInput.removeAttribute('disabled'); 
    } else {
        // Disable it (won't be sent in $_POST)
        fieldset.setAttribute('disabled', 'disabled');
        body.classList.add('is-disabled');
        
        // Update button appearance
        btn.textContent = 'Edit';
        btn.classList.remove('btn-is-editing');

        if (listInput) listInput.setAttribute('disabled', 'disabled');
    }
}

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
    document.getElementById('pub_date').required = isOld;
}

function toggleDateGroup() {
    const isOld = document.getElementById('type_old').checked;
    document.getElementById('old-date-group').style.display = isOld ? 'block' : 'none';
    document.getElementById('pub_date').required = isOld;
}

function toggleFullText() {
    const mainFile = document.getElementById('main_file').files.length;
    const supplFile = document.getElementById('supplemental_file').files.length;
    const showFullText = (mainFile === 0 && supplFile === 0);

    const block = document.getElementById('block-fulltext');
    const body = block.querySelector('.block-body');
    const fieldset = body.querySelector('fieldset');

    // Check if the block is currently disabled
    const isCurrentlyDisabled = fieldset.hasAttribute('disabled');

    if (showFullText && isCurrentlyDisabled) {
        // Enable it for editing
        fieldset.removeAttribute('disabled');
        body.classList.remove('is-disabled');
        block.style.display = 'block';
    } else if (!showFullText && !isCurrentlyDisabled) {
        // Disable it (won't be sent in $_POST)
        fieldset.setAttribute('disabled', 'disabled');
        body.classList.add('is-disabled');
        block.style.display = 'none';
    }
}

// Initialize visibility
toggleFullText();

// --- Authors Logic ---
const authorsContainer = document.getElementById('authors-container');

function createAuthorRow(author = {pub_name: '', mID: '', Core_ID: '', is_manual: true}) {
    const row = document.createElement('div');
    row.className = 'author-row';

    row.innerHTML = `
        <div class="move-btns">
            <button type="button" class="btn-up">↑</button>
            <button type="button" class="btn-down">↓</button>
        </div>
        <input type="text" class="auth-pub-name" placeholder="Publication Name" required value="${author.pub_name}" ${author.is_manual ? '' : 'readonly'}>
        <input type="text" class="auth-core-id" placeholder="CORE-ID" value="${author.Core_ID}" readonly ${author.is_manual ? 'disabled' : ''}>
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
        body: JSON.stringify({ 
            text: textarea.value,
            csrf_token: csrfToken 
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP Error status: ${response.status}`);
        }
        return response.json();
    })
    .then(members => {
        if (!members || members.length === 0) {
            alert("No members found matching those IDs.");
            return; // Return early so we don't need a giant 'else' block
        }
        const batchAffIds = document.getElementById('batch-aff-ids').value.trim();        
        members.forEach(m => {
            const row = createAuthorRow({
                pub_name: m.pub_name,
                mID: m.mID,
                Core_ID: m.Core_ID,
                is_manual: false
            });
            
            if (batchAffIds) {
                row.querySelector('.auth-aff-refs').value = batchAffIds;
            }
            
            document.getElementById('authors-container').appendChild(row);
        });

        if (typeof autoDistributeDuties === 'function') {
            autoDistributeDuties();
        }
        textarea.value = '';
        document.getElementById('batch-aff-ids').value = '';                
    })
    .catch(error => {
        console.error("Fetch or parsing failed:", error);
        alert("A network or server error occurred. Check the browser console.");
    })
    .finally(() => {
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

document.getElementById('btn-add-manual').onclick = () => {
    const row = createAuthorRow();
    insertAuthorAtPosition(row);
};

document.getElementById('btn-add-myself').onclick = () => {
    if (!USER_DATA.mID) {
        alert('You must be logged in to add yourself as an author.');
        return;
    }

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
        Core_ID: USER_DATA.Core_ID,
        is_manual: false
    });
    insertAuthorAtPosition(row);
};

// Pre-populated data for edit/revise modes
// EDIT_AUTHORS, EDIT_BRANCHES, EDIT_LINKS are defined in upload.php

document.addEventListener('DOMContentLoaded', () => {
    // Authors
    if (EDIT_AUTHORS && EDIT_AUTHORS.authors && EDIT_AUTHORS.authors.length > 0) {
        const affContainer = document.getElementById('affiliations-container');
        if (EDIT_AUTHORS.affiliations) {
            EDIT_AUTHORS.affiliations.forEach(aff => {
                addAffiliationRow(aff[1]);
            });
        }
        EDIT_AUTHORS.authors.forEach(a => {
            const row = createAuthorRow({
                pub_name: a[0] || '',
                mID: a[1] || '',
                Core_ID: '',
                is_manual: !a[1]
            });
            row.querySelector('.auth-duty').value = a[2] || 100;
            if (a[3] && a[3].length > 0) {
                row.querySelector('.auth-aff-refs').value = a[3].join(',');
            }
            authorsContainer.appendChild(row);
        });
        updateDutySummary();
    } else if (USER_DATA.mID) {
        const row = createAuthorRow({
            pub_name: USER_DATA.pub_name,
            mID: USER_DATA.mID,
            Core_ID: USER_DATA.Core_ID,
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
        document.getElementById('pub_date').required = true;
    }
});

// --- Affiliations & Links ---
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
// BRANCHES_DATA is defined in upload.php
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
    if (count >= DOC_BRANCH_MAX) {
        alert('Maximum of ' + DOC_BRANCH_MAX + ' branches allowed.');
        return;
    }
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

    const base = Math.floor(100 / count);
    const remainder = 100 - base * count;

    rows.forEach((row, idx) => {
        const input = row.querySelector('.branch-impact');
        input.value = idx === 0 ? base + remainder : base;
    });

    updateBranchSummary();

    document.getElementById('btn-add-branch').style.display = count >= DOC_BRANCH_MAX ? 'none' : '';
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
