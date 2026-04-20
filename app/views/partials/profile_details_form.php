<?php
/**
 * Shared Profile Details Form Partial
 * 
 * Includes Recommended and Optional details used in registration and profile completion.
 * Assumes $institutions and $researchBranches are available.
 */
?>
<details>
    <summary>Recommended Details</summary>
    <div class="form-group">
        <label for="display_name">Display Name:</label>
        <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($_POST['display_name'] ?? ''); ?>">
    </div>
    <div class="form-group">
        <label for="pub_name">Preferred Name for Publications:</label>
        <input type="text" id="pub_name" name="pub_name" value="<?php echo htmlspecialchars($_POST['pub_name'] ?? ''); ?>">
    </div>
	<div class="form-group">
		<label for="institution_search">Primary Institution:</label>
		
		<input type="text" 
			   id="institution_search" 
			   class="form-control" 
			   placeholder="Type to search institutions..." 
			   autocomplete="off" 
			   value="<?php echo htmlspecialchars($selectedInstitutionName ?? ''); ?>">
		
		<input type="hidden" name="iID" id="iID" value="<?php echo (int)($_POST['iID'] ?? 1); ?>">

		<div id="institution_results" class="autocomplete-dropdown" style="display: none;"></div>
	</div>

    <div class="form-group">
        <label>Work Areas:</label>
        <div class="research-area-list">
            <?php 
            $postWorkAreas = $_POST['work_areas'] ?? [];
            $postWorkPublic = $_POST['work_areas_public'] ?? [];
            foreach ($researchBranches as $branch): 
                $bID = (string)$branch['bID'];
                $checked = in_array($bID, $postWorkAreas) ? 'checked' : '';
                $publicChecked = in_array($bID, $postWorkPublic) || empty($_POST) ? 'checked' : '';
            ?>
                <div class="area-row">
                    <div class="area-info">
                        <input type="checkbox" name="work_areas[]" value="<?php echo $bID; ?>" <?php echo $checked; ?> id="work_<?php echo $bID; ?>">
                        <label for="work_<?php echo $bID; ?>"><?php echo htmlspecialchars($branch['abbr'] . ' (' . $branch['bname'] . ')'); ?></label>
                    </div>
                    <div class="area-privacy">
                        <input type="checkbox" name="work_areas_public[]" value="<?php echo $bID; ?>" <?php echo $publicChecked; ?> id="work_pub_<?php echo $bID; ?>">
                        <label for="work_pub_<?php echo $bID; ?>">Public</label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group">
        <label>Interest Areas:</label>
        <div class="research-area-list">
            <?php 
            $postIntAreas = $_POST['interest_areas'] ?? [];
            $postIntPublic = $_POST['interest_areas_public'] ?? [];
            foreach ($researchBranches as $branch): 
                $bID = (string)$branch['bID'];
                $checked = in_array($bID, $postIntAreas) ? 'checked' : '';
                $publicChecked = in_array($bID, $postIntPublic) || empty($_POST) ? 'checked' : '';
            ?>
                <div class="area-row">
                    <div class="area-info">
                        <input type="checkbox" name="interest_areas[]" value="<?php echo $bID; ?>" <?php echo $checked; ?> id="int_<?php echo $bID; ?>">
                        <label for="int_<?php echo $bID; ?>"><?php echo htmlspecialchars($branch['abbr'] . ' (' . $branch['bname'] . ')'); ?></label>
                    </div>
                    <div class="area-privacy">
                        <input type="checkbox" name="interest_areas_public[]" value="<?php echo $bID; ?>" <?php echo $publicChecked; ?> id="int_pub_<?php echo $bID; ?>">
                        <label for="int_pub_<?php echo $bID; ?>">Public</label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group">
        <label>EMail Subscriptions for Daily Updates:</label>
        <div class="research-area-list">
            <?php 
            $postMailAreas = $_POST['mail_areas'] ?? [];
            foreach ($researchBranches as $branch): 
                $bID = (string)$branch['bID'];
                $checked = in_array($bID, $postMailAreas) ? 'checked' : '';
            ?>
                <div class="area-row">
                    <div class="area-info">
                        <input type="checkbox" name="mail_areas[]" value="<?php echo $bID; ?>" <?php echo $checked; ?> id="mail_<?php echo $bID; ?>">
                        <label for="mail_<?php echo $bID; ?>"><?php echo htmlspecialchars($branch['abbr'] . ' (' . $branch['bname'] . ')'); ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group">
        <label for="timezone">Timezone:</label>
        <select id="timezone" name="timezone">
            <?php
            $tzList = DateTimeZone::listIdentifiers();
            $tzOptions = [];
            $now = new DateTime('now', new DateTimeZone('UTC'));
            
            foreach ($tzList as $tz) {
                $now->setTimezone(new DateTimeZone($tz));
                $offset = $now->getOffset();
                $hours = floor($offset / 3600);
                $minutes = abs(($offset % 3600) / 60);
                $offsetStr = sprintf('UTC%+03d:%02d', $hours, $minutes);
                $tzOptions[$tz] = "($offsetStr) $tz";
            }
            asort($tzOptions);
            
            $selectedTz = $_POST['timezone'] ?? 'UTC';
            if ($selectedTz === '+00:00') $selectedTz = 'UTC';

            foreach ($tzOptions as $val => $label) {
                $selected = ($val === $selectedTz) ? 'selected' : '';
                echo "<option value=\"$val\" $selected>" . htmlspecialchars($label) . "</option>";
            }
            ?>
        </select>
    </div>
</details>

<details>
    <summary>Optional Details</summary>
    <div class="form-group">
        <label for="full_name">Full Name:</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_full_name" name="meta_public[full_name]" value="1" <?php echo isset($_POST['meta_public']['full_name']) ? 'checked' : ''; ?>>
            <label for="public_full_name">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="other_names">Other Names (semicolon-separated):</label>
        <input type="text" id="other_names" name="other_names" value="<?php echo htmlspecialchars($_POST['other_names'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_other_names" name="meta_public[other_names]" value="1" <?php echo isset($_POST['meta_public']['other_names']) ? 'checked' : ''; ?>>
            <label for="public_other_names">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="prefix">Prefix:</label>
        <input type="text" id="prefix" name="prefix" value="<?php echo htmlspecialchars($_POST['prefix'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_prefix" name="meta_public[prefix]" value="1" <?php echo isset($_POST['meta_public']['prefix']) ? 'checked' : ''; ?>>
            <label for="public_prefix">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="suffix">Suffix:</label>
        <input type="text" id="suffix" name="suffix" value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_suffix" name="meta_public[suffix]" value="1" <?php echo isset($_POST['meta_public']['suffix']) ? 'checked' : ''; ?>>
            <label for="public_suffix">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="position">Position:</label>
        <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_position" name="meta_public[position]" value="1" <?php echo isset($_POST['meta_public']['position']) ? 'checked' : ''; ?>>
            <label for="public_position">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="affiliations">Affiliations (semicolon-separated):</label>
        <input type="text" id="affiliations" name="affiliations" value="<?php echo htmlspecialchars($_POST['affiliations'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_affiliations" name="meta_public[affiliations]" value="1" <?php echo isset($_POST['meta_public']['affiliations']) ? 'checked' : ''; ?>>
            <label for="public_affiliations">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="address">Address:</label>
        <textarea id="address" name="address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
        <div class="checkbox-group">
            <input type="checkbox" id="public_address" name="meta_public[address]" value="1" <?php echo isset($_POST['meta_public']['address']) ? 'checked' : ''; ?>>
            <label for="public_address">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="url1">Professional Homepage:</label>
        <input type="url" id="url1" name="url1" value="<?php echo htmlspecialchars($_POST['url1'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_url1" name="meta_public[url1]" value="1" <?php echo isset($_POST['meta_public']['url1']) ? 'checked' : ''; ?>>
            <label for="public_url1">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="url2">Personal Homepage:</label>
        <input type="url" id="url2" name="url2" value="<?php echo htmlspecialchars($_POST['url2'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_url2" name="meta_public[url2]" value="1" <?php echo isset($_POST['meta_public']['url2']) ? 'checked' : ''; ?>>
            <label for="public_url2">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="education">Education (year, degree, major, institution=iID; ...):</label>
        <input type="text" id="education" name="education" value="<?php echo htmlspecialchars($_POST['education'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_education" name="meta_public[education]" value="1" <?php echo isset($_POST['meta_public']['education']) ? 'checked' : ''; ?>>
            <label for="public_education">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="cv">CV Link:</label>
        <input type="url" id="cv" name="cv" value="<?php echo htmlspecialchars($_POST['cv'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_cv" name="meta_public[cv]" value="1" <?php echo isset($_POST['meta_public']['cv']) ? 'checked' : ''; ?>>
            <label for="public_cv">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="research_statement">Research Statement:</label>
        <textarea id="research_statement" name="research_statement"><?php echo htmlspecialchars($_POST['research_statement'] ?? ''); ?></textarea>
        <div class="checkbox-group">
            <input type="checkbox" id="public_research_statement" name="meta_public[research_statement]" value="1" <?php echo isset($_POST['meta_public']['research_statement']) ? 'checked' : ''; ?>>
            <label for="public_research_statement">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="other_interests">Other Interests:</label>
        <input type="text" id="other_interests" name="other_interests" value="<?php echo htmlspecialchars($_POST['other_interests'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_other_interests" name="meta_public[other_interests]" value="1" <?php echo isset($_POST['meta_public']['other_interests']) ? 'checked' : ''; ?>>
            <label for="public_other_interests">Make public</label>
        </div>
    </div>
    <div class="form-group">
        <label for="mstatus">Member Status (e.g., retired, on vacation):</label>
        <input type="text" id="mstatus" name="mstatus" value="<?php echo htmlspecialchars($_POST['mstatus'] ?? ''); ?>">
        <div class="checkbox-group">
            <input type="checkbox" id="public_mstatus" name="meta_public[mstatus]" value="1" <?php echo isset($_POST['meta_public']['mstatus']) ? 'checked' : ''; ?>>
            <label for="public_mstatus">Make public</label>
        </div>
    </div>
</details>
