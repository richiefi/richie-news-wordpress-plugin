/**
 * Collection Preview Modal Component
 *
 * Shows a full preview of the collection feed with articles in their actual layout.
 */

import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const PLACEHOLDER_IMAGE = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23ddd" width="400" height="300"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="48" dy="10.5" font-weight="bold" x="50%25" y="50%25" text-anchor="middle"%3E%3F%3C/text%3E%3C/svg%3E';

export default function CollectionPreviewModal( { collectionId, onClose } ) {
	const [ feed, setFeed ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! collectionId ) return;

		setIsLoading( true );
		setError( null );

		// Fetch the actual feed from the REST API
		apiFetch( {
			path: `/richie/v1/preview-feed/${ collectionId }`,
		} )
			.then( ( response ) => {
				setFeed( response );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to fetch feed preview:', err );
				setError( err.message || 'Failed to load preview' );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ collectionId ] );

	const renderArticle = ( article, index, headerTitle = '' ) => {
		// Layout is at the top level in API response, not in article_attributes
		const layout = article.layout || 'small';
		const kicker = article.kicker || '';
		const title = article.title || __( 'Untitled', 'richie' );
		const summary = article.summary !== undefined ? ( article.summary || '' ) : '';
		const image = article.image_url || PLACEHOLDER_IMAGE;
		const bgColor = article.background_color ? `#${ article.background_color }` : null;

		const articleStyle = bgColor ? { backgroundColor: bgColor } : {};

		const articleClassName = `preview-article preview-article--${ layout }${
			bgColor ? ' preview-article--has-bg' : ''
		}`;

		if ( layout === 'featured' ) {
			return (
				<div key={ index } className={ articleClassName } style={ articleStyle }>
					{ headerTitle && <h3 className="preview-header-title">{ headerTitle }</h3> }
					<img src={ image } alt="" className="preview-article-image" />
					{ kicker && <div className="preview-article-kicker">{ kicker }</div> }
					<h2 className="preview-article-title">{ title }</h2>
					{ summary && <p className="preview-article-summary">{ summary }</p> }
				</div>
			);
		}

		// Default: small layout (horizontal with image on right)
		return (
			<div key={ index } className={ articleClassName } style={ articleStyle }>
				{ headerTitle && <h3 className="preview-header-title">{ headerTitle }</h3> }
				<div className="preview-article-content">
					<div className="preview-article-text">
						{ kicker && <div className="preview-article-kicker">{ kicker }</div> }
						<h4 className="preview-article-title">{ title }</h4>
						{ summary && <p className="preview-article-summary">{ summary }</p> }
					</div>
					<img src={ image } alt="" className="preview-article-image" />
				</div>
			</div>
		);
	};

	const renderAdSlot = ( ad, index ) => {
		const provider = ad.ad_provider || 'unknown';
		return (
			<div key={ index } className="preview-ad">
				<div className="preview-ad-label">
					{ __( 'Advertisement', 'richie' ) } ({ provider })
				</div>
			</div>
		);
	};

	return (
		<Modal
			title={ __( 'Collection Preview', 'richie' ) }
			onRequestClose={ onClose }
			className="collection-preview-modal"
		>
			<div className="collection-preview-notice">
				<p>{ __( 'Preview shows the feed structure and layout types. Actual appearance depends on the native app configuration.', 'richie' ) }</p>
			</div>
			<div className="collection-preview-content">
				{ isLoading ? (
					<div className="collection-preview-loading">
						<Spinner />
						<p>{ __( 'Loading preview...', 'richie' ) }</p>
					</div>
				) : error ? (
					<div className="collection-preview-error">
						<p>{ error }</p>
					</div>
				) : feed && feed.articles && feed.articles.length > 0 ? (
					<div className="collection-preview-feed">
						{ ( () => {
							let lastHeaderTitle = null;
							return feed.articles.map( ( item, index ) => {
								// Check if it's an ad slot (layout field is at top level)
								if ( item.layout === 'ad' ) {
									lastHeaderTitle = null;
									return renderAdSlot( item, index );
								}

								const rawHeaderTitle = item.collection_header_title || '';
								const showHeaderTitle =
									rawHeaderTitle && rawHeaderTitle !== lastHeaderTitle;
								lastHeaderTitle = rawHeaderTitle || null;
								const headerTitle = showHeaderTitle ? rawHeaderTitle : '';

								return renderArticle( item, index, headerTitle );
							} );
						} )() }
					</div>
				) : (
					<div className="collection-preview-empty">
						<p>{ __( 'No articles in this collection', 'richie' ) }</p>
					</div>
				) }
			</div>

			<div className="collection-preview-footer">
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Close', 'richie' ) }
				</Button>
			</div>
		</Modal>
	);
}
