/**
 * Section Modal Component
 *
 * Modal dialog for adding/editing a news section.
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Modal,
	Button,
	TextControl,
	SelectControl,
	CheckboxControl,
	ColorPicker,
	__experimentalNumberControl as NumberControl,
	Popover,
	Spinner,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const DEFAULT_LAYOUT_OPTIONS = [
	{ label: 'Featured', value: 'featured' },
	{ label: 'Small', value: 'small' },
];

const LAYOUT_OPTIONS =
	typeof window !== 'undefined' &&
	window.richieFeedEditorSettings &&
	Array.isArray( window.richieFeedEditorSettings.layoutOptions )
		? window.richieFeedEditorSettings.layoutOptions.map( ( option ) => ( {
			label: option.label || option.title || option.value,
			value: option.value,
		} ) )
		: DEFAULT_LAYOUT_OPTIONS;

const ORDER_BY_OPTIONS = [
	{ label: __( 'Date', 'richie' ), value: 'date' },
	{ label: __( 'Modified', 'richie' ), value: 'modified' },
	{ label: __( 'Title', 'richie' ), value: 'title' },
	{ label: __( 'Author', 'richie' ), value: 'author' },
	{ label: __( 'ID', 'richie' ), value: 'id' },
];

const ORDER_DIRECTION_OPTIONS = [
	{ label: __( 'Descending', 'richie' ), value: 'DESC' },
	{ label: __( 'Ascending', 'richie' ), value: 'ASC' },
];

const MAX_AGE_OPTIONS = [
	{
		label: sprintf( _n( '%d day', '%d days', 1, 'richie' ), 1 ),
		value: '1 day',
	},
	{
		label: sprintf( _n( '%d day', '%d days', 3, 'richie' ), 3 ),
		value: '3 days',
	},
	{
		label: sprintf( _n( '%d week', '%d weeks', 1, 'richie' ), 1 ),
		value: '1 week',
	},
	{
		label: sprintf( _n( '%d week', '%d weeks', 2, 'richie' ), 2 ),
		value: '2 weeks',
	},
	{
		label: sprintf( _n( '%d month', '%d months', 1, 'richie' ), 1 ),
		value: '1 month',
	},
	{
		label: sprintf( _n( '%d month', '%d months', 3, 'richie' ), 3 ),
		value: '3 months',
	},
	{
		label: sprintf( _n( '%d month', '%d months', 6, 'richie' ), 6 ),
		value: '6 months',
	},
	{
		label: sprintf( _n( '%d year', '%d years', 1, 'richie' ), 1 ),
		value: '1 year',
	},
	{ label: __( 'All time', 'richie' ), value: 'all_time' },
];

const defaultFormData = {
	name: '',
	number_of_posts: 5,
	post_type: 'post',
	categories: [],
	tags: '',
	order_by: 'date',
	order_direction: 'DESC',
	max_age: 'all_time',
	list_layout_style: 'small',
	list_group_title: '',
	background_color: '',
	allow_duplicates: false,
	disable_summary: false,
};

export default function SectionModal( {
	section,
	collectionId,
	onSave,
	onClose,
} ) {
	const [ formData, setFormData ] = useState( defaultFormData );
	const [ categories, setCategories ] = useState( [] );
	const [ postTypes, setPostTypes ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );

	const isEditing = !! section;

	// Load categories and post types
	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/wp/v2/categories?per_page=100' } ),
			apiFetch( { path: '/richie/v1/editor/post-types' } ),
		] )
			.then( ( [ categoriesResponse, postTypesResponse ] ) => {
				setCategories( categoriesResponse || [] );
				setPostTypes( postTypesResponse || [] );
			} )
			.catch( ( err ) => {
				console.error( 'Failed to fetch options:', err );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [] );

	// Populate form when editing
	useEffect( () => {
		if ( section ) {
			// Handle max_age: convert empty string or missing value to 'all_time'
			let maxAge = section.max_age;
			if ( ! maxAge || maxAge === '' ) {
				maxAge = 'all_time';
			}

			setFormData( {
				name: section.name || '',
				number_of_posts: section.number_of_posts || 5,
				post_type: section.post_type || 'post',
				categories: section.categories || [],
				tags: Array.isArray( section.tags )
					? section.tags.join( ', ' )
					: section.tags || '',
				order_by: section.order_by || 'date',
				order_direction: section.order_direction || 'DESC',
				max_age: maxAge,
				list_layout_style: section.list_layout_style || 'small',
				list_group_title: section.list_group_title || '',
				background_color: section.background_color || '',
				allow_duplicates: !! section.allow_duplicates,
				disable_summary: !! section.disable_summary,
			} );
		}
	}, [ section ] );

	const updateField = ( field, value ) => {
		setFormData( ( prev ) => ( { ...prev, [ field ]: value } ) );
	};

	const [ isColorPickerOpen, setIsColorPickerOpen ] = useState( false );

	const handleBackgroundColorChange = ( value ) => {
		if ( ! value ) {
			updateField( 'background_color', '' );
			return;
		}
		const normalized = value.startsWith( '#' ) ? value.slice( 1 ) : value;
		updateField( 'background_color', normalized );
	};

	const handleSubmit = () => {
		setIsSaving( true );

		// Convert tags string to array
		const tagsArray = formData.tags
			? formData.tags
					.split( ',' )
					.map( ( t ) => t.trim() )
					.filter( Boolean )
			: [];

		onSave( {
			...formData,
			tags: tagsArray,
			article_set: collectionId,
		} )
			.catch( ( err ) => {
				console.error( 'Failed to save section:', err );
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	};

	const handleCategoryToggle = ( categoryId ) => {
		setFormData( ( prev ) => {
			const current = prev.categories || [];
			const updated = current.includes( categoryId )
				? current.filter( ( id ) => id !== categoryId )
				: [ ...current, categoryId ];
			return { ...prev, categories: updated };
		} );
	};

	if ( isLoading ) {
		return (
			<Modal
				title={
					isEditing
						? __( 'Edit section', 'richie' )
						: __( 'Add section', 'richie' )
				}
				onRequestClose={ onClose }
			>
				<div className="section-modal-loading">
					<Spinner />
				</div>
			</Modal>
		);
	}

	return (
		<Modal
			title={
				isEditing
					? __( 'Edit section', 'richie' )
					: __( 'Add section', 'richie' )
			}
			onRequestClose={ onClose }
			className="section-modal"
		>
			<div className="section-modal-content">
				<div className="section-modal-row">
					<TextControl
						label={ __( 'Name', 'richie' ) }
						value={ formData.name }
						onChange={ ( value ) => updateField( 'name', value ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						required
					/>
				</div>

				<div className="section-modal-row section-modal-row--two-col">
					<div className="section-modal-field">
						<NumberControl
							label={ __( 'Number of Articles', 'richie' ) }
							value={ formData.number_of_posts }
							onChange={ ( value ) =>
								updateField( 'number_of_posts', parseInt( value, 10 ) )
							}
							__next40pxDefaultSize
							min={ 1 }
							max={ 50 }
						/>
						<p className="section-modal-help">
							{ __( 'Number of posts included in the feed', 'richie' ) }
						</p>
					</div>

					<SelectControl
						label={ __( 'Layout Style', 'richie' ) }
						value={ formData.list_layout_style }
						options={ LAYOUT_OPTIONS }
						onChange={ ( value ) =>
							updateField( 'list_layout_style', value )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="section-modal-row">
					<SelectControl
						label={ __( 'Post Type', 'richie' ) }
						value={ formData.post_type }
						options={ postTypes.map( ( pt ) => ( {
							label: pt.label,
							value: pt.name,
						} ) ) }
						onChange={ ( value ) => updateField( 'post_type', value ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="section-modal-row section-modal-row--two-col">
					<SelectControl
						label={ __( 'Order By', 'richie' ) }
						value={ formData.order_by }
						options={ ORDER_BY_OPTIONS }
						onChange={ ( value ) => updateField( 'order_by', value ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={ __( 'Order Direction', 'richie' ) }
						value={ formData.order_direction }
						options={ ORDER_DIRECTION_OPTIONS }
						onChange={ ( value ) =>
							updateField( 'order_direction', value )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="section-modal-row">
					<SelectControl
						label={ __( 'Max Age', 'richie' ) }
						value={ formData.max_age }
						options={ MAX_AGE_OPTIONS }
						onChange={ ( value ) => updateField( 'max_age', value ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</div>

				<div className="section-modal-row">
					<label className="components-base-control__label">
						{ __( 'Categories', 'richie' ) }
					</label>
					<div className="category-checkboxes">
						{ categories.map( ( category ) => (
							<CheckboxControl
								key={ category.id }
								label={ category.name }
								checked={ formData.categories.includes(
									category.id
								) }
								onChange={ () =>
									handleCategoryToggle( category.id )
								}
								__nextHasNoMarginBottom
							/>
						) ) }
					</div>
				</div>

				<div className="section-modal-row">
					<TextControl
						label={ __( 'Tags (comma-separated)', 'richie' ) }
						value={ formData.tags }
						onChange={ ( value ) => updateField( 'tags', value ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						help={ __(
							'Enter tag slugs separated by commas',
							'richie'
						) }
					/>
				</div>

				<div className="section-modal-row">
					<TextControl
						label={ __( 'Group Title', 'richie' ) }
						value={ formData.list_group_title }
						onChange={ ( value ) =>
							updateField( 'list_group_title', value )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						help={ __(
							'Header to display before the story, useful on the first small_group_item of a group',
							'richie'
						) }
					/>
				</div>

				<div className="section-modal-row section-modal-row--color">
					<label className="components-base-control__label">
						{ __( 'Background Color', 'richie' ) }
					</label>
					<div className="section-modal-color-picker">
						<Button
							variant="secondary"
							onClick={ () =>
								setIsColorPickerOpen( ! isColorPickerOpen )
							}
						>
							{ formData.background_color
								? `#${ formData.background_color }`
								: __( 'Select color', 'richie' ) }
						</Button>
						{ isColorPickerOpen && (
							<Popover
								placement="bottom-start"
								onClose={ () => setIsColorPickerOpen( false ) }
							>
								<ColorPicker
									color={
										formData.background_color
											? `#${ formData.background_color }`
											: undefined
									}
									onChangeComplete={ ( value ) =>
										handleBackgroundColorChange( value.hex )
									}
									disableAlpha
								/>
							</Popover>
						) }
						<Button
							variant="secondary"
							onClick={ () => handleBackgroundColorChange( '' ) }
							disabled={ ! formData.background_color }
						>
							{ __( 'Clear', 'richie' ) }
						</Button>
					</div>
					<p className="section-modal-color-help">
						{ __(
							'Background color to be used with layout types. Not all layout types support this.',
							'richie'
						) }
					</p>
				</div>

				<div className="section-modal-row section-modal-row--checkboxes">
					<div className="section-modal-field">
						<CheckboxControl
							label={ __( 'Allow Duplicates', 'richie' ) }
							checked={ formData.allow_duplicates }
							onChange={ ( value ) =>
								updateField( 'allow_duplicates', value )
							}
							__nextHasNoMarginBottom
						/>
						<p className="section-modal-help">
							{ __( 'Allow duplicate articles in this source', 'richie' ) }
						</p>
					</div>

					<div className="section-modal-field">
						<CheckboxControl
							label={ __( 'Disable Summary', 'richie' ) }
							checked={ formData.disable_summary }
							onChange={ ( value ) =>
								updateField( 'disable_summary', value )
							}
							__nextHasNoMarginBottom
						/>
						<p className="section-modal-help">
							{ __( 'Do not show summary text in news list', 'richie' ) }
						</p>
					</div>
				</div>
			</div>

			<div className="section-modal-footer">
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Cancel', 'richie' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleSubmit }
					isBusy={ isSaving }
					disabled={ isSaving || ! formData.name }
				>
					{ isEditing
						? __( 'Update section', 'richie' )
						: __( 'Add section', 'richie' ) }
				</Button>
			</div>
		</Modal>
	);
}
