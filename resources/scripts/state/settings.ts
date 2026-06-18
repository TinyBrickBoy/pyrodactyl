import { Action, action } from 'easy-peasy';

export interface SiteSettings {
    name: string;
    locale: string;
    timezone: string;
    sso?: {
        enabled: boolean;
        displayName: string;
        authenticated: boolean;
    };
}

export interface SettingsStore {
    data?: SiteSettings;
    setSettings: Action<SettingsStore, SiteSettings>;
}

const settings: SettingsStore = {
    data: undefined,
    setSettings: action((state, payload) => {
        state.data = payload;
    }),
};

export default settings;
