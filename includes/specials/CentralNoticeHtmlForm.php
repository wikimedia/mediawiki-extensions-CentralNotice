<?php

class CentralNoticeHtmlForm extends HTMLForm {
	/**
	 * Get the whole body of the form.
	 * @return string
	 */
	public function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'cn-formsection-' );
	}
}
