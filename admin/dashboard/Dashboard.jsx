import { __ } from '@wordpress/i18n';
import {
	useState,
	useReducer,
	useContext,
	useCallback,
	useEffect,
} from '@wordpress/element';
import {
	__experimentalInputControl as InputControl,
	Button,
	__experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
	SVG,
	Path,
	RadioControl,
} from '@wordpress/components';
import { pick } from 'lodash';

import { clsx } from 'clsx/lite';
import {
	initialSettings,
	SETTINGS_ACTION,
	settingsReducer,
} from './SettingsReducer';
import {
	DashboardActionsContext,
	DashboardContext,
	SettingsContext,
	SettingsDispatchContext,
} from './SettingsContext';
import { fetchSettings, pushSettings } from './api';

export function Dashboard() {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isInit, setIsInit ] = useState( true );

	const [ settings, dispatch ] = useReducer(
		settingsReducer,
		initialSettings
	);

	const pullSettings = useCallback( async () => {
		setIsLoading( true );
		setIsInit( true );
		try {
			const savedSettings = await fetchSettings();
			dispatch( {
				type: SETTINGS_ACTION.applyPatch,
				value: savedSettings,
			} );
			setIsInit( false );
		} catch ( e ) {
			console.log( e );
		} finally {
			setIsLoading( false );
		}
	}, [ dispatch ] );

	const saveSettings = useCallback(
		async ( settingsToSave ) => {
			console.log( settingsToSave );
			setIsLoading( true );
			try {
				const savedSettings = await pushSettings( settingsToSave );

				dispatch( {
					type: SETTINGS_ACTION.applyPatch,
					value: savedSettings,
				} );
			} catch ( e ) {
				console.log( e );
			} finally {
				setIsLoading( false );
			}
		},
		[ dispatch ]
	);

	useEffect( () => {
		pullSettings();
	}, [ pullSettings ] );

	function LoadingComponent() {
		return ! isLoading ? (
			<Button
				onClick={ () => {
					pullSettings();
				} }
			>
				{ __(
					'Try again to get the settings.',
					'glotpress-ai-extension'
				) }
			</Button>
		) : (
			<>
				<p>
					{ __( 'Loading the settings…', 'glotpress-ai-extension' ) }
				</p>
			</>
		);
	}

	return (
		<DashboardContext.Provider value={ { isLoading } }>
			<DashboardActionsContext.Provider
				value={ { saveSettings, pullSettings } }
			>
				<SettingsContext.Provider value={ settings }>
					<SettingsDispatchContext.Provider value={ dispatch }>
						<div className="flex flex-col gap-8 p-4 max-w-[1200px]">
							<h1 className="text-2xl mb-0">
								{ __(
									'GlotPress AI Add-On Settings',
									'glotpress-ai-extension'
								) }
							</h1>
							<Overview />
							{ ! isInit ? <Settings /> : <LoadingComponent /> }
						</div>
					</SettingsDispatchContext.Provider>
				</SettingsContext.Provider>
			</DashboardActionsContext.Provider>
		</DashboardContext.Provider>
	);
}

function Overview() {
	return (
		<div>
			<div className="grid grid-cols-2 gap-8">
				<StatCard
					title={ __(
						'Translations Started',
						'glotpress-ai-extension'
					) }
					value={ 30 }
					bgColor={ 'bg-teal-100' }
				/>
				<StatCard
					title={ __( 'Tokens Used', 'glotpress-ai-extension' ) }
					value={ 345345 }
					bgColor={ 'bg-red-100' }
				/>
			</div>
		</div>
	);
}

function StatCard( { icon, title, value, bgColor } ) {
	return (
		<div className={ clsx( 'flex flex-col gap-2 p-4 rounded', bgColor ) }>
			{ icon }
			<span className="text-2xl">{ value }</span>
			<h3 className="text-lg leading-none m-0">{ title }</h3>
		</div>
	);
}

function Settings() {
	const settings = useContext( SettingsContext );
	const dispatch = useContext( SettingsDispatchContext );
	const { isLoading } = useContext( DashboardContext );
	const { saveSettings } = useContext( DashboardActionsContext );
	const [ editApiKey, setEditApiKey ] = useState( false );

	function setAPIKey( value ) {
		dispatch( {
			type: SETTINGS_ACTION.set,
			key: 'open_ai_key',
			value,
		} );
	}

	function setModel( value ) {
		dispatch( {
			type: SETTINGS_ACTION.set,
			key: 'open_ai_model',
			value,
		} );
	}

	return (
		<div className="flex flex-col gap-4">
			<div className="max-w-1/2">
				<InputControl
					__next40pxDefaultSize
					label={ __( 'Open AI API Key', 'glotpress-ai-extension' ) }
					value={ settings.open_ai_key }
					onChange={ ( value ) => {
						setAPIKey( value ?? '' );
					} }
					placeholder={ settings.open_ai_key_masked }
					type="text"
					disabled={ ! editApiKey }
					suffix={
						<InputControlSuffixWrapper variant="control">
							<Button
								icon={
									editApiKey ? (
										<SVG
											viewBox="0 0 24 24"
											xmlns="http://www.w3.org/2000/svg"
										>
											<Path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z" />
										</SVG>
									) : (
										'edit'
									)
								}
								label={
									! editApiKey
										? __(
												'Change',
												'glotpress-ai-extension'
										  )
										: __(
												'Cancel',
												'glotpress-ai-extension'
										  )
								}
								size="small"
								onClick={ () =>
									setEditApiKey( ( prev ) => {
										const toggle = ! prev;
										if ( ! toggle ) {
											setAPIKey( '' );
										}
										return toggle;
									} )
								}
							/>
						</InputControlSuffixWrapper>
					}
				/>
			</div>

			<RadioControl
				label={ __( 'AI Model', 'glotpress-ai-extension' ) }
				selected={ settings.open_ai_model }
				onChange={ ( value ) => {
					setModel( value );
				} }
				options={ [
					{
						label: __( 'GPT-4.1 mini', 'glotpress-ai-extension' ),
						value: 'gpt-4.1-mini',
						description: __(
							'Faster and cheaper than full GPT-4.1 — great balance of cost and quality.',
							'glotpress-ai-extension'
						),
					},
					{
						label: __( 'GPT-4.1 nano', 'glotpress-ai-extension' ),
						value: 'gpt-4.1-nano',
						description: __(
							'Ultra-fast and low-cost — best for lightweight and high-traffic chats.',
							'glotpress-ai-extension'
						),
					},
				] }
			/>
			<div>
				<Button
					variant="primary"
					disabled={ isLoading }
					isBusy={ isLoading }
					onClick={ () => {
						const settingsToSave = [ 'open_ai_model' ];
						if ( editApiKey ) {
							settingsToSave.push( 'open_ai_key' );
						}
						saveSettings( pick( settings, settingsToSave ) );
					} }
				>
					{ __( 'Save', 'glotpress-ai-extension' ) }
				</Button>
			</div>
		</div>
	);
}
