<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Chart;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Config\Config;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use StatusValue;

/**
 * JS-only Special page for creating and modifying Chart definition pages.
 */
class SpecialChartWizard extends FormSpecialPage {

	protected bool $isChartWizardEnabled;
	private ?Title $title = null;
	private bool $isNew;
	private ?array $chartDefinition;

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
		$this->chartDefinition = $this->preExecute( $par );
		if ( $this->chartDefinition === null ) {
			return;
		}

		parent::execute( $par );

		$this->getOutput()->setPageTitleMsg(
			$this->msg( $this->isNew ? 'creating' : 'editing' )
				->rawParams( $this->title->getPrefixedText() )
		);

		$this->getOutput()->addJsConfigVars( [
			'chartDefinition' => $this->chartDefinition,
			'chartIsNew' => $this->isNew,
			'chartPageName'   => $this->title->getPrefixedText(),
			'chartEditToken' => $this->getContext()->getCsrfTokenSet()->getToken()->toString(),
			'chartBaseRevId' => $this->title->getLatestRevID(),
		] );
		$this->getOutput()->addModules( 'ext.chart.wizard' );
		$this->getOutput()->addModuleStyles( 'ext.chart.wizard.styles' );
	}

	/**
	 * Validations and setup to run to before calling parent::execute().
	 *
	 * @param ?string $par The subpage parameter, which should be the name of the Chart definition page to edit.
	 * @return ?array Chart definition, or null if we should error out.
	 */
	private function preExecute( ?string $par ): ?array {
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
		$chartDefinition = json_decode( $content?->getText() ?? '{}', associative: true );

		// Keep track of the page title of the 'source' dataset.
		if ( isset( $chartDefinition['source'] ) ) {
			$this->sourcePrefixedText = Title::makeTitle( ns: NS_DATA, title: $chartDefinition['source'] )
				->getPrefixedText();
		}

		$action = $this->isNew ? 'Creating' : 'Editing';
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
			->setHeaderHtml(
				Html::openElement( 'div', [ 'class' => 'ext-chart-wizard-container' ] ) .
				Html::openElement( 'fieldset', [
					'class' => 'cdx-field cdx-field--is-fieldset ext-chart-wizard__form',
					'disabled' => true,
				] )
			)
			->setMessagePrefix( 'chart-wizard-form' )
			->setSections( [
				'source-label' => [
					'description-message' => 'chart-wizard-form-source-description',
				],
			] )
			->setSubmitTextMsg( $this->isNew ? 'chart-wizard-publish' : 'publishchanges' )
			->setFooterHtml(
				Html::closeElement( 'fieldset' ) . $this->getPreviewHtml() .
				Html::closeElement( 'div' )
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
	public function onSubmit( array $data, ?HTMLForm $form = null ): Status|StatusValue {
		// Grab data directly from POST request, until ::getFormFields() encompasses the whole form.
		$data = $form->getRequest()->getPostValues();

		$this->logger->debug( __METHOD__ . ': submitting data {0}', [ json_encode( $data ) ] );

		// Merge new definition into the existing one, to preserve any fields not exposed in the form.
		$newDefinition = array_merge(
			(array)$this->chartDefinition,
			json_decode( $data['chartDefinition'], true ),
		);

		// Save using the API (the only safe way to edit server-side,
		// since PageUpdater does not run edit filters, etc.).
		$context = new DerivativeContext( $this->getContext() );
		$context->setRequest( new DerivativeRequest( $this->getRequest(), [
			'action' => 'edit',
			'title' => $this->title->getPrefixedText(),
			'text' => json_encode( $newDefinition ),
			'contentmodel' => JCChartContent::CONTENT_MODEL,
			'baserevid' => $data['baseRevId'],
			'errorformat' => 'html',
			'token' => $context->getCsrfTokenSet()->getToken(),
		] ) );
		$api = new ApiMain( $context, true );

		try {
			$api->execute();
		} catch ( ApiUsageException $e ) {
			$this->logger->debug(
				__METHOD__ . ': API edit failed for {0}: {1}',
				[ $this->title->__toString(), $e->getMessage() ]
			);
			return $e->getStatusValue();
		}

		$apiData = $api->getResult()->getResultData()['edit'];
		if ( $apiData['newrevid'] ?? false ) {
			// Show post-edit message.
			$revId = $apiData['newrevid'];
			$postEditKey = EditPage::POST_EDIT_COOKIE_KEY_PREFIX . $revId;
			$this->getRequest()->response()->setCookie(
				name: $postEditKey,
				value: $this->isNew ? 'created' : 'saved',
				expire: time() + EditPage::POST_EDIT_COOKIE_DURATION
			);

			// Set session var to be read by Hooks::onRecentChanges_save(),
			// which will add the 'Chart Wizard' change tag to the revision.
			$this->getRequest()->getSession()->set( Hooks::SESSION_KEY, true );
		}

		$this->getOutput()->redirect(
			$apiData['tempusercreatedredirect'] ?? $this->title->getLocalURL()
		);

		return Status::newGood( $api->getResult() );
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return false;
	}

	/** @inheritDoc */
	protected function getDisplayFormat(): string {
		return 'codex';
	}
}
