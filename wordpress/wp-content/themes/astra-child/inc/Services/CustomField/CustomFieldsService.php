<?php

namespace AstraChild\Services\CustomField;

class CustomFieldsService
{
	/**
	 * Process custom fields data.
	 *
	 * @param mixed $customFields Raw custom fields data.
	 * @return mixed Processed custom fields data.
	 */
	public function processCustomFields(mixed $customFields): mixed
	{
		try {
			// Process WYSIWYG fields
			$wysiwyg_fields = ['tentang_perusahaan', 'deskripsi_pekerjaan', 'persyaratan', 'cara_melamar', 'benefit'];
			foreach ($wysiwyg_fields as $field) {
				if (!empty($customFields[$field]) && is_string($customFields[$field])) {
					$customFields[$field] = do_shortcode(wpautop(wp_kses_post($customFields[$field])));
				}
			}

			// Process number fields
			$number_fields = ['umur_min', 'umur_max', 'pengalaman', 'gaji_minimal', 'gaji_maksimal'];
			foreach ($number_fields as $field) {
				if (!empty($customFields[$field]) && (is_numeric($customFields[$field]) || is_string($customFields[$field]))) {
					$customFields[$field] = is_numeric($customFields[$field]) ? (int)$customFields[$field] : null;
				}
			}

			// Process email, URL, and text fields (handle arrays for cloned fields)
			foreach (['email_kontak', 'situs_kontak', 'nomor_kontak'] as $field) {
				if (!empty($customFields[$field])) {
					if (is_array($customFields[$field])) {
						$customFields[$field] = array_map(function ($value) use ($field) {
							if ($field === 'email_kontak') {
								return is_string($value) ? sanitize_email($value) : '';
							}
							if ($field === 'situs_kontak') {
								return is_string($value) ? esc_url($value) : '';
							}
							if ($field === 'nomor_kontak') {
								return is_string($value) ? sanitize_text_field($value) : '';
							}
							return $value;
						}, $customFields[$field]);
					} else {
						if ($field === 'email_kontak' && is_string($customFields[$field])) {
							$customFields[$field] = sanitize_email($customFields[$field]);
						}
						if ($field === 'situs_kontak' && is_string($customFields[$field])) {
							$customFields[$field] = esc_url($customFields[$field]);
						}
						if ($field === 'nomor_kontak' && is_string($customFields[$field])) {
							$customFields[$field] = sanitize_text_field($customFields[$field]);
						}
					}
				}
			}

			// Process date fields
			if (!empty($customFields['deadline']) && is_string($customFields['deadline'])) {
				$customFields['deadline'] = date('d-m-Y', strtotime($customFields['deadline']));
			}

			// Process fieldset fields (e.g., social media)
			if (!empty($customFields['social_media'])) {
				$socialMediaData = $customFields['social_media'];

				// If it's a JSON string, decode it
				if (is_string($socialMediaData)) {
					$socialMediaData = json_decode($socialMediaData, true);
				}

				$processedSocialMedia = [];

				// Flatten all sets and keep only non-empty usernames, last non-empty wins
				if (is_array($socialMediaData)) {
					foreach ($socialMediaData as $platformSet) {
						if (is_array($platformSet)) {
							foreach ($platformSet as $platform => $username) {
								if (is_string($platform) && is_string($username)) {
									$platform = sanitize_text_field(trim($platform));
									$username = sanitize_text_field(trim($username));
									if (!empty($platform) && !empty($username)) {
										if (!isset($processedSocialMedia[$platform])) {
											$processedSocialMedia[$platform] = [];
										}
										$processedSocialMedia[$platform][] = $username;
									}
								}
							}
						}
					}
				}

				$customFields['social_media'] = $processedSocialMedia;
			}

			return $customFields;
		} catch (\Exception $e) {
			error_log('CustomFieldsService::processCustomFields error: ' . $e->getMessage());
			return $customFields; // Return original data on error
		}
	}
}
