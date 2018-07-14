<?php

/**
 * Class CentralNoticeHtmlForm
 */
class CentralNoticeHtmlForm extends HTMLForm {
	/**
	 * Get the whole body of the form.
	 * @return string
	 */
	function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'cn-formsection-' );
	}
}
