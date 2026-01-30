<?php

namespace WPLokerBJM\Views\Page;
use WPLokerBJM\Presenters\Pages\SinglePresenter;
use WPLokerBJM\Presenters\DocumentHTML;

class SingleLowonganView
{
	
	public function __construct(
		private SinglePresenter $singlePresenter
	) {
	}

	public function render(): void
	{
		$data = $this->singlePresenter->getSingleData(get_the_ID());
		DocumentHTML::renderDocument($data['schema'], $data['props'], $data['seoHtml']);
	}
}
