<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Chart;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * JS-only Special page for creating and modifying Chart definition pages.
 */
class SpecialChartWizard extends FormSpecialPage {

	protected bool $isChartWizardEnabled;
	private ?Title $title = null;
	private bool $isNew;

	/**
	 * The prefixed (including ns) page title supplied in the 'source' field
	 * of the chart definition.
	 *
	 * @var ?string
	 */
	private ?string $sourcePrefixedText = null;

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		Config $config,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct( 'ChartWizard' );
		$this->isChartWizardEnabled = $config->get( 'ChartWizardEnabled' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$chartDefinition = $this->preExecute( $par );
		if ( $chartDefinition === null ) {
			return;
		}

		parent::execute( $par );

		$this->getOutput()->setPageTitleMsg(
			$this->msg( $this->isNew ? 'editing' : 'creating' )
				->rawParams( $this->title->getPrefixedText() )
		);

		$this->getOutput()->addJsConfigVars( [
			'chartDefinition' => $chartDefinition,
			'chartIsNew' => $this->isNew,
		] );
		$this->getOutput()->addModules( 'ext.chart.wizard' );
		$this->getOutput()->addModuleStyles( 'ext.chart.wizard.styles' );
	}

	/**
	 * Validations and setup to run to before calling parent::execute().
	 *
	 * @param ?string $par The subpage parameter, which should be the name of the Chart definition page to edit.
	 * @return ?stdClass Chart definition object, or null if we should error out.
	 */
	private function preExecute( ?string $par ): ?stdClass {
		// Check feature flag.
		if ( !$this->isChartWizardEnabled ) {
			$this->setHeaders();
			$this->getOutput()->addWikiMsg( 'chart-wizard-disabled' );
			return null;
		}
		// Check that the subpage parameter refers to a valid chart definition page.
		$this->title = Title::newFromText( (string)$par, NS_DATA );
		// TODO: Add input to create a new chart definition JSON page when one wasn't provided.
		if ( $this->title?->getContentModel() !== JCChartContent::CONTENT_MODEL ) {
			$this->getOutput()->showErrorPage(
				'chart-wizard-error',
				'chart-error-chart-definition-not-found',
				[ $this->title?->getPrefixedText(), $this->title?->getSubpageText() ],
				$this->sourcePrefixedText
			);
			$this->logger->debug(
				__METHOD__ . ': Chart definition page not found or invalid content model: {0}',
				[ $this->title?->__toString() ]
			);
			return null;
		}

		$this->isNew = !$this->title->exists();

		// This adds all the navigation tabs in the skin as if this were a proper `action` page.
		$this->getSkin()->setRelevantTitle( $this->title );

		// Fetch the chart definition page contents.
		$wikiPage = $this->wikiPageFactory->newFromTitle( $this->title );
		/** @var JCChartContent $content */
		$content = $wikiPage->getContent();
		'@phan-var JCChartContent $content';
		$chartDefinition = json_decode( $content?->getText() ?? '{}' );

		// Keep track of the page title of the 'source' dataset.
		if ( isset( $chartDefinition->source ) ) {
			$this->sourcePrefixedText = Title::makeTitle( NS_DATA, $chartDefinition->source )
				->getPrefixedText();
		}

		$action = $this->title->exists() ? 'Editing' : 'Creating';
		$this->logger->debug(
			__METHOD__ . ": $action chart definition page: {0}, source: {1}",
			[ $this->title->__toString(), $this->sourcePrefixedText ]
		);

		return $chartDefinition;
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		return [
			'source' => [
				'section' => 'source-label',
				'type' => 'text',
				'name' => 'source',
				'cssclass' => 'ext-chart-wizard__source',
				'placeholder-message' => 'chart-wizard-form-source-placeholder',
				'required' => true,
				'default' => $this->sourcePrefixedText,
			],
		];
	}

	/** @inheritDoc */
	protected function alterForm( HTMLForm $form ) {
		$form->setId( 'ext-chart-wizard' )
			->setTitle( $this->getPageTitle( $this->title->getPrefixedDBkey() ) )
			->setHeaderHtml( Html::openElement( 'fieldset', [
				'class' => 'cdx-field cdx-field--is-fieldset ext-chart-wizard__form',
				'disabled' => true,
			] ) )
			->setMessagePrefix( 'chart-wizard-form' )
			->setSections( [
				'source-label' => [
					'description-message' => 'chart-wizard-form-source-description',
				],
			] )
			->setSubmitTextMsg( $this->isNew ? 'chart-wizard-publish' : 'publishchanges' )
			->setFooterHtml(
				Html::closeElement( 'fieldset' ) . $this->getPreviewHtml()
			);
	}

	private function getPreviewHtml(): string {
		// TODO: Show JSON or Chart SVG instead of placeholder as appropriate.
		$placeholderImage = Html::element( 'div', [ 'class' => 'ext-chart-wizard__preview-placeholder-image' ] );
		$placeholderHeader = Html::element(
			'div',
			[ 'class' => 'ext-chart-wizard__preview-placeholder-header' ],
			$this->msg( 'chart-wizard-preview-placeholder-title' )->text()
		);
		$placeholderDescription = Html::element(
			'div',
			[],
			$this->msg( 'chart-wizard-preview-placeholder-description' )->text()
		);
		$contentsHtml = Html::rawElement(
			'div',
			[ 'class' => 'ext-chart-wizard__preview-placeholder' ],
			$placeholderImage . $placeholderHeader . $placeholderDescription
		);
		return Html::rawElement( 'div', [ 'class' => 'ext-chart-wizard__preview' ], $contentsHtml );
	}

	/** @inheritDoc */
	public function checkExecutePermissions( User $user ): void {
		parent::checkExecutePermissions( $user );
		// Redirect to 'View source' if the user doesn't have permission to edit the page.
		if ( !$this->getUser()->definitelyCan( 'edit', $this->title ) ) {
			$this->logger->debug(
				__METHOD__ . ': User {0} does not have permission to edit {1}, redirecting to view source',
				[ $this->getUser()->getName(), $this->title->__toString() ]
			);
			$this->getOutput()->redirect( $this->title->getEditURL() );
		}
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'edit';
	}

	/** @inheritDoc */
	public function onSubmit( array $data, ?HTMLForm $form = null ): Status {
		// TODO: implement; Be sure to merge fields with the preexisting chart definition as applicable
		return Status::newGood();
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return false;
	}

	/** @inheritDoc */
	protected function getDisplayFormat() {
		return 'codex';
	}
}
