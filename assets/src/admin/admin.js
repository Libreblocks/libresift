/**
 * LibreSift admin: list and editor of saved filters (kind "filter" of the LibreForge definitions API).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner, TextControl, ToggleControl } from '@wordpress/components';
import { createRoot, useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs, getQueryArg } from '@wordpress/url';

import { AppShell, Badge, CardGrid, Collapsible, EditorColumns, EmptyState, ItemCard, PageHeader, SettingRow } from '@libreblocks/ui';

import './admin.scss';

const BASE = '/libreblocks/v1/forge';
const data = window.libresiftAdmin || {};

const slugify = ( text ) =>
	String( text || '' )
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_+|_+$/g, '' )
		.slice( 0, 40 );

const SOURCES = [
	{ value: 'taxonomy', label: __( 'A taxonomy (categories, cities…)', 'libresift' ), icon: 'tag' },
	{ value: 'meta', label: __( 'A custom field (price, rooms…)', 'libresift' ), icon: 'forms' },
	{ value: 'search', label: __( 'Text search', 'libresift' ), icon: 'search' },
	{ value: 'sort', label: __( 'Sorting (newest, cheapest…)', 'libresift' ), icon: 'sort' },
];

const CONTROLS = {
	taxonomy: [
		{ value: 'checkboxes', label: __( 'Checkboxes (choose many)', 'libresift' ) },
		{ value: 'radio', label: __( 'Single choice buttons', 'libresift' ) },
		{ value: 'select', label: __( 'Dropdown list', 'libresift' ) },
	],
	meta: [
		{ value: 'checkboxes', label: __( 'Checkboxes (choose many)', 'libresift' ) },
		{ value: 'radio', label: __( 'Single choice buttons', 'libresift' ) },
		{ value: 'select', label: __( 'Dropdown list', 'libresift' ) },
		{ value: 'range', label: __( 'Range (from / to)', 'libresift' ) },
		{ value: 'text', label: __( 'Search inside this field', 'libresift' ) },
	],
	search: [ { value: 'search', label: __( 'Search box', 'libresift' ) } ],
	sort: [
		{ value: 'select', label: __( 'Dropdown list', 'libresift' ) },
		{ value: 'radio', label: __( 'Single choice buttons', 'libresift' ) },
	],
};

const DEFAULTS = { title: '', label: '', source: 'taxonomy', control: 'checkboxes', taxonomy: '', meta_key: '', value_type: 'CHAR', options_source: 'auto', options: [], hide_empty: true, all_label: '', placeholder: '', min: null, max: null, step: 1, prefix: '', suffix: '', sort_options: [], target: '' };

/* -------------------------------------------------------------------------- rows */

function Text( { label, help, value, onChange, placeholder, error, type = 'text' } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<TextControl label={ label } hideLabelFromVision type={ type } value={ value ?? '' } placeholder={ placeholder } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

function Choose( { label, help, value, options, onChange, error } ) {
	return (
		<SettingRow label={ label } help={ help } error={ error }>
			<SelectControl label={ label } hideLabelFromVision value={ value } options={ options } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

function Toggle( { label, help, checked, onChange } ) {
	return (
		<SettingRow label={ label } help={ help }>
			<ToggleControl label={ <span className="screen-reader-text">{ label }</span> } checked={ !! checked } onChange={ onChange } __nextHasNoMarginBottom />
		</SettingRow>
	);
}

/** Editable rows of objects with the given columns. */
function Rows( { rows = [], columns, empty, onChange, addLabel } ) {
	const update = ( index, patch ) => onChange( rows.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) );

	return (
		<div className="ls-rows">
			{ rows.map( ( row, index ) => (
				<div className="ls-rows__row" key={ index }>
					{ columns.map( ( column ) =>
						column.options ? (
							<SelectControl key={ column.key } label={ column.label } hideLabelFromVision value={ row[ column.key ] } options={ column.options } onChange={ ( value ) => update( index, { [ column.key ]: value } ) } __nextHasNoMarginBottom />
						) : (
							<TextControl key={ column.key } label={ column.label } hideLabelFromVision placeholder={ column.label } value={ row[ column.key ] ?? '' } onChange={ ( value ) => update( index, { [ column.key ]: value } ) } __nextHasNoMarginBottom />
						)
					) }
					<Button icon="trash" isDestructive label={ __( 'Remove', 'libresift' ) } onClick={ () => onChange( rows.filter( ( _, i ) => i !== index ) ) } />
				</div>
			) ) }
			<Button variant="secondary" icon="plus-alt2" onClick={ () => onChange( [ ...rows, { ...empty } ] ) }>
				{ addLabel }
			</Button>
		</div>
	);
}

/* -------------------------------------------------------------------------- editor sections */

function sections( { config, set, errors, env, slug, setSlug, isNew } ) {
	const source = config.source || 'taxonomy';
	const control = config.control;
	const fields = ( env.fields || [] ).filter( ( field ) => ! [ 'repeater', 'group', 'media', 'gallery', 'wysiwyg' ].includes( field.type ) );
	const known = fields.some( ( field ) => field.value === config.meta_key );

	const setTitle = ( title ) => {
		if ( isNew && ( ! slug || slug === slugify( config.title ) ) ) {
			setSlug( slugify( title ) );
		}
		set( { title } );
	};

	const setSource = ( value ) => set( { source: value, control: CONTROLS[ value ][ 0 ].value } );

	const setField = ( meta_key ) => {
		const field = fields.find( ( item ) => item.value === meta_key );
		const numeric = field && field.type === 'number';
		set( { meta_key, value_type: numeric ? 'NUMERIC' : 'CHAR', control: numeric ? 'range' : config.control } );
	};

	const list = [
		{
			id: 'general',
			title: __( 'General settings', 'libresift' ),
			icon: 'admin-generic',
			open: true,
			keys: [ 'title', 'slug', 'taxonomy', 'meta_key' ],
			content: (
				<>
					<Text label={ __( 'Filter name', 'libresift' ) } help={ __( 'Ex.: City, Price range, Search.', 'libresift' ) } value={ config.title } placeholder={ __( 'City', 'libresift' ) } error={ errors.title } onChange={ setTitle } />
					<Choose label={ __( 'Filter by', 'libresift' ) } value={ source } options={ SOURCES } onChange={ setSource } />
					{ source === 'taxonomy' && (
						<Choose label={ __( 'Taxonomy', 'libresift' ) } value={ config.taxonomy } error={ errors.taxonomy } options={ [ { value: '', label: __( '— Choose —', 'libresift' ) }, ...( env.taxonomies || [] ) ] } onChange={ ( taxonomy ) => set( { taxonomy } ) } />
					) }
					{ source === 'meta' && (
						<>
							<Choose
								label={ __( 'Custom field', 'libresift' ) }
								error={ errors.meta_key }
								value={ known || ! config.meta_key ? config.meta_key : '__custom' }
								options={ [ { value: '', label: __( '— Choose —', 'libresift' ) }, ...fields.map( ( field ) => ( { value: field.value, label: `${ field.label } (${ field.value })` } ) ), { value: '__custom', label: __( 'Other (type the name)…', 'libresift' ) } ] }
								onChange={ ( value ) => ( value === '__custom' ? set( { meta_key: ' ' } ) : setField( value ) ) }
							/>
							{ ! known && config.meta_key !== '' && <Text label={ __( 'Field name', 'libresift' ) } value={ config.meta_key.trim() } onChange={ ( value ) => set( { meta_key: slugify( value ) } ) } /> }
						</>
					) }
					<Choose label={ __( 'Looks like', 'libresift' ) } value={ control } options={ CONTROLS[ source ] } onChange={ ( value ) => set( { control: value } ) } />
					<Text label={ __( 'Title shown on the site', 'libresift' ) } help={ __( 'Empty = the filter name.', 'libresift' ) } value={ config.label } placeholder={ config.title } onChange={ ( label ) => set( { label } ) } />
				</>
			),
		},
	];

	if ( [ 'checkboxes', 'radio', 'select' ].includes( control ) && source !== 'sort' ) {
		list.push( {
			id: 'options',
			title: __( 'Options', 'libresift' ),
			icon: 'list-view',
			open: true,
			keys: [],
			content: (
				<>
					<Choose
						label={ __( 'Options come from', 'libresift' ) }
						help={ source === 'taxonomy' ? __( 'Automatic = every term of the taxonomy.', 'libresift' ) : __( 'Automatic = the options of the field, or the values already saved.', 'libresift' ) }
						value={ config.options_source }
						options={ [
							{ value: 'auto', label: __( 'Automatic (recommended)', 'libresift' ) },
							{ value: 'manual', label: __( 'A list I type', 'libresift' ) },
						] }
						onChange={ ( options_source ) => set( { options_source } ) }
					/>
					{ config.options_source === 'manual' && (
						<SettingRow label={ __( 'Options', 'libresift' ) } help={ __( 'The "value" must match what is saved (term slug or field value).', 'libresift' ) } stacked>
							<Rows
								rows={ config.options }
								columns={ [ { key: 'value', label: __( 'Value', 'libresift' ) }, { key: 'label', label: __( 'Label', 'libresift' ) } ] }
								empty={ { value: '', label: '' } }
								addLabel={ __( 'Add option', 'libresift' ) }
								onChange={ ( options ) => set( { options } ) }
							/>
						</SettingRow>
					) }
					{ source === 'taxonomy' && <Toggle label={ __( 'Hide terms without items', 'libresift' ) } checked={ config.hide_empty } onChange={ ( hide_empty ) => set( { hide_empty } ) } /> }
					{ control === 'radio' && <Text label={ __( '"All" option text', 'libresift' ) } value={ config.all_label } placeholder={ __( 'All', 'libresift' ) } onChange={ ( all_label ) => set( { all_label } ) } /> }
					{ control === 'select' && <Text label={ __( 'First option text', 'libresift' ) } value={ config.placeholder } placeholder={ __( 'All', 'libresift' ) } onChange={ ( placeholder ) => set( { placeholder } ) } /> }
				</>
			),
		} );
	}

	if ( control === 'range' ) {
		list.push( {
			id: 'range',
			title: __( 'Range', 'libresift' ),
			icon: 'leftright',
			open: true,
			keys: [],
			content: (
				<>
					<p className="ls-intro">{ __( 'Empty limits are found automatically from the saved values.', 'libresift' ) }</p>
					<Text label={ __( 'Lowest value', 'libresift' ) } type="number" value={ config.min ?? '' } placeholder={ __( 'Automatic', 'libresift' ) } onChange={ ( value ) => set( { min: value === '' ? null : Number( value ) } ) } />
					<Text label={ __( 'Highest value', 'libresift' ) } type="number" value={ config.max ?? '' } placeholder={ __( 'Automatic', 'libresift' ) } onChange={ ( value ) => set( { max: value === '' ? null : Number( value ) } ) } />
					<Text label={ __( 'Step', 'libresift' ) } help={ __( 'Ex.: 1000 for prices, 1 for rooms.', 'libresift' ) } type="number" value={ config.step ?? 1 } onChange={ ( value ) => set( { step: Number( value ) || 1 } ) } />
					<Text label={ __( 'Before the number', 'libresift' ) } value={ config.prefix } placeholder="R$" onChange={ ( prefix ) => set( { prefix } ) } />
					<Text label={ __( 'After the number', 'libresift' ) } value={ config.suffix } placeholder="m²" onChange={ ( suffix ) => set( { suffix } ) } />
				</>
			),
		} );
	}

	if ( source === 'search' || control === 'text' ) {
		list.push( {
			id: 'search',
			title: __( 'Search box', 'libresift' ),
			icon: 'search',
			open: true,
			keys: [],
			content: <Text label={ __( 'Placeholder', 'libresift' ) } value={ config.placeholder } placeholder={ __( 'Search…', 'libresift' ) } onChange={ ( placeholder ) => set( { placeholder } ) } />,
		} );
	}

	if ( source === 'sort' ) {
		list.push( {
			id: 'sorting',
			title: __( 'Sorting options', 'libresift' ),
			icon: 'sort',
			open: true,
			keys: [ 'sort_options' ],
			content: (
				<SettingRow label={ __( 'Options', 'libresift' ) } help={ __( 'Ex.: "Lowest price" = custom field (number) price, ascending.', 'libresift' ) } error={ errors.sort_options } stacked>
					<Rows
						rows={ config.sort_options }
						columns={ [
							{ key: 'label', label: __( 'Text shown', 'libresift' ) },
							{
								key: 'orderby',
								label: __( 'Sort by', 'libresift' ),
								options: [
									{ value: 'date', label: __( 'Date', 'libresift' ) },
									{ value: 'title', label: __( 'Title', 'libresift' ) },
									{ value: 'menu_order', label: __( 'Manual order', 'libresift' ) },
									{ value: 'meta_value_num', label: __( 'Custom field (number)', 'libresift' ) },
									{ value: 'meta_value', label: __( 'Custom field (text)', 'libresift' ) },
									{ value: 'rand', label: __( 'Random', 'libresift' ) },
								],
							},
							{
								key: 'order',
								label: __( 'Order', 'libresift' ),
								options: [
									{ value: 'ASC', label: __( 'Ascending', 'libresift' ) },
									{ value: 'DESC', label: __( 'Descending', 'libresift' ) },
								],
							},
							{ key: 'meta_key', label: __( 'Field name (if any)', 'libresift' ) },
						] }
						empty={ { label: '', orderby: 'date', order: 'DESC', meta_key: '' } }
						addLabel={ __( 'Add sorting option', 'libresift' ) }
						onChange={ ( sort_options ) => set( { sort_options } ) }
					/>
				</SettingRow>
			),
		} );
	}

	list.push( {
		id: 'advanced',
		title: __( 'Advanced settings', 'libresift' ),
		icon: 'admin-tools',
		keys: [ 'slug' ],
		content: (
			<>
				<Text
					label={ __( 'Name in the address', 'libresift' ) }
					help={ sprintf( /* translators: %s: example address */ __( 'The address shows the choice, e.g. %s', 'libresift' ), `?f_${ slug || 'city' }=…` ) }
					value={ slug }
					error={ errors.slug }
					onChange={ ( value ) => setSlug( slugify( value ) ) }
				/>
				<Text
					label={ __( 'Only for the listing', 'libresift' ) }
					help={ __( 'Optional. The "Listing ID" set on a Loop Grid. Empty = every listing on the page.', 'libresift' ) }
					value={ config.target }
					placeholder="imoveis"
					onChange={ ( target ) => set( { target: target.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) } ) }
				/>
				{ source === 'meta' && (
					<Choose
						label={ __( 'Compare values as', 'libresift' ) }
						value={ config.value_type }
						options={ [
							{ value: 'CHAR', label: __( 'Text', 'libresift' ) },
							{ value: 'NUMERIC', label: __( 'Numbers', 'libresift' ) },
						] }
						onChange={ ( value_type ) => set( { value_type } ) }
					/>
				) }
			</>
		),
	} );

	return list;
}

/* -------------------------------------------------------------------------- screens */

function useRoute() {
	const read = () => ( { view: getQueryArg( window.location.href, 'view' ) || 'list', id: Number( getQueryArg( window.location.href, 'id' ) ) || 0 } );
	const [ route, setRoute ] = useState( read );

	useEffect( () => {
		const onPop = () => setRoute( read() );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [] );

	const go = useCallback( ( view, id = 0 ) => {
		window.history.pushState( {}, '', addQueryArgs( window.location.pathname, view === 'list' ? { page: 'libresift' } : { page: 'libresift', view, id: id || undefined } ) );
		setRoute( { view, id } );
		window.scrollTo( 0, 0 );
	}, [] );

	return [ route, go ];
}

const sourceIcon = ( source ) => SOURCES.find( ( item ) => item.value === source )?.icon || 'filter';

function List( { onEdit, onCreate } ) {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );

	const load = () =>
		apiFetch( { path: addQueryArgs( `${ BASE }/definitions`, { kind: 'filter' } ) } )
			.then( setItems )
			.catch( ( err ) => setError( err.message ) );

	useEffect( () => {
		load();
	}, [] );

	const remove = ( item ) => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Delete this filter? Pages that show it will hide it.', 'libresift' ) ) ) {
			apiFetch( { path: `${ BASE }/definitions/${ item.id }`, method: 'DELETE' } ).then( load ).catch( ( err ) => setError( err.message ) );
		}
	};

	return (
		<>
			<PageHeader
				icon="filter"
				title={ __( 'Filters', 'libresift' ) }
				subtitle={ __( 'Let visitors narrow down your listings by city, price, category…', 'libresift' ) }
				actions={
					<Button variant="primary" icon="plus-alt2" onClick={ onCreate }>
						{ __( 'New filter', 'libresift' ) }
					</Button>
				}
			/>
			{ error && <Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice> }
			{ ! items && ! error && <Spinner /> }
			{ items && items.length === 0 && (
				<EmptyState
					icon="filter"
					title={ __( 'Create your first filter', 'libresift' ) }
					text={ __( 'Make a filter here, then add the "Filter" block or widget next to a Loop Grid. Results update instantly.', 'libresift' ) }
					action={
						<Button variant="primary" icon="plus-alt2" onClick={ onCreate }>
							{ __( 'New filter', 'libresift' ) }
						</Button>
					}
				/>
			) }
			{ items && items.length > 0 && (
				<CardGrid columns={ 3 }>
					{ items.map( ( item ) => (
						<ItemCard
							key={ item.id }
							icon={ sourceIcon( item.config.source ) }
							title={ item.title }
							code={ `f_${ item.slug }` }
							status={ item.status === 'active' ? <Badge tone="success">{ __( 'Active', 'libresift' ) }</Badge> : <Badge>{ __( 'Draft', 'libresift' ) }</Badge> }
							description={ `[libresift_filter id="${ item.id }"]` }
							onClick={ () => onEdit( item.id ) }
							actions={
								<>
									<Button variant="secondary" size="small" onClick={ () => onEdit( item.id ) }>
										{ __( 'Edit', 'libresift' ) }
									</Button>
									<Button icon="trash" size="small" isDestructive label={ __( 'Delete', 'libresift' ) } onClick={ () => remove( item ) } />
								</>
							}
						/>
					) ) }
				</CardGrid>
			) }
		</>
	);
}

const snapshot = ( item ) => JSON.stringify( { slug: item.slug, status: item.status, config: item.config } );

function Editor( { id, onBack, onCreated } ) {
	const [ env, setEnv ] = useState( null );
	const [ item, setItem ] = useState( id ? null : { slug: '', status: 'active', config: DEFAULTS } );
	const [ errors, setErrors ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const saved = useRef( id ? null : snapshot( { slug: '', status: 'active', config: DEFAULTS } ) );

	useEffect( () => {
		Promise.all( [ apiFetch( { path: `${ BASE }/environment` } ), apiFetch( { path: `${ BASE }/blocks-data` } ) ] )
			.then( ( [ environment, blocks ] ) => setEnv( { taxonomies: environment.taxonomies, fields: blocks.fields } ) )
			.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) );

		if ( id ) {
			apiFetch( { path: `${ BASE }/definitions/${ id }` } )
				.then( ( loaded ) => {
					saved.current = snapshot( loaded );
					setItem( loaded );
				} )
				.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) );
		}
	}, [ id ] );

	if ( ! env || ! item ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.message }</Notice> : <Spinner />;
	}

	const set = ( patch ) => setItem( ( current ) => ( { ...current, config: { ...current.config, ...patch } } ) );
	const setSlug = ( slug ) => setItem( ( current ) => ( { ...current, slug } ) );
	const dirty = snapshot( item ) !== saved.current || ! item.id;
	const list = sections( { config: { ...DEFAULTS, ...item.config }, set, errors, env, slug: item.slug, setSlug, isNew: ! item.id } );

	const save = () => {
		setSaving( true );
		setErrors( {} );
		const request = item.id
			? apiFetch( { path: `${ BASE }/definitions/${ item.id }`, method: 'PUT', data: { slug: item.slug, status: item.status, config: item.config } } )
			: apiFetch( { path: `${ BASE }/definitions`, method: 'POST', data: { kind: 'filter', slug: item.slug, status: item.status, config: item.config } } );

		request
			.then( ( result ) => {
				saved.current = snapshot( result );
				setItem( result );
				setNotice( { status: 'success', message: __( 'Saved.', 'libresift' ) } );

				if ( ! item.id ) {
					onCreated( result.id );
				}
			} )
			.catch( ( err ) => {
				setErrors( err?.data?.errors || {} );
				setNotice( { status: 'error', message: err.message } );
			} )
			.finally( () => setSaving( false ) );
	};

	return (
		<>
			<PageHeader
				back={ { label: __( 'Filters', 'libresift' ), onClick: onBack } }
				icon={ sourceIcon( item.config.source ) }
				title={ item.config.title || __( 'New filter', 'libresift' ) }
				subtitle={ item.slug ? <code>{ `?f_${ item.slug }=` }</code> : '' }
			/>
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) } className="ls-notice-bar">
					{ notice.message }
					{ Object.keys( errors ).length > 0 && (
						<ul>
							{ Object.values( errors ).map( ( message, index ) => (
								<li key={ index }>{ message }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }
			<EditorColumns
				main={ list.map( ( section ) => (
					<Collapsible key={ section.id } title={ section.title } icon={ section.icon } open={ section.open } hasError={ section.keys.some( ( key ) => errors[ key ] ) }>
						{ section.content }
					</Collapsible>
				) ) }
				aside={
					<>
						<div className="lb-card ls-aside">
							<h3>{ __( 'Actions', 'libresift' ) }</h3>
							<Button variant="primary" className="ls-aside__save" onClick={ save } isBusy={ saving } disabled={ saving }>
								{ item.id ? __( 'Save changes', 'libresift' ) : __( 'Create filter', 'libresift' ) }
							</Button>
							<p className={ `ls-aside__status${ dirty ? ' is-dirty' : '' }` }>{ dirty ? __( 'Unsaved changes', 'libresift' ) : __( 'All changes saved', 'libresift' ) }</p>
							<ToggleControl label={ __( 'Active', 'libresift' ) } checked={ item.status === 'active' } onChange={ ( on ) => setItem( { ...item, status: on ? 'active' : 'draft' } ) } __nextHasNoMarginBottom />
							<Button variant="link" onClick={ onBack }>
								{ __( '← Back to filters', 'libresift' ) }
							</Button>
						</div>
						<div className="lb-card ls-aside">
							<h3>{ __( 'How to show it', 'libresift' ) }</h3>
							<ol>
								<li>{ __( 'Save the filter.', 'libresift' ) }</li>
								<li>{ __( 'On the page with the Loop Grid, add the "Filter" block (or Elementor widget) and choose this filter.', 'libresift' ) }</li>
								<li>{ __( 'Publish. The results update as visitors choose.', 'libresift' ) }</li>
							</ol>
							{ item.id > 0 && (
								<p>
									{ __( 'Shortcode:', 'libresift' ) } <code>{ `[libresift_filter id="${ item.id }"]` }</code>
								</p>
							) }
						</div>
					</>
				}
			/>
		</>
	);
}

function App() {
	const [ route, go ] = useRoute();

	return (
		<AppShell product={ { name: 'LibreSift', icon: data.icon || 'filter' } } tabs={ [ { key: 'filters', label: __( 'Filters', 'libresift' ), href: addQueryArgs( window.location.pathname, { page: 'libresift' } ), icon: 'filter' } ] } current="filters">
			{ route.view === 'edit' || route.view === 'new' ? (
				<Editor key={ route.id || 'new' } id={ route.id } onBack={ () => go( 'list' ) } onCreated={ ( id ) => go( 'edit', id ) } />
			) : (
				<List onEdit={ ( id ) => go( 'edit', id ) } onCreate={ () => go( 'new' ) } />
			) }
		</AppShell>
	);
}

const mount = document.getElementById( 'libresift-app' );

if ( mount ) {
	createRoot( mount ).render( <App /> );
}
