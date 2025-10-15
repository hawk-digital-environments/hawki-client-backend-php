import type {DockerContext} from './DockerContext.js';
import type {Installer} from './installer/Installer.js';
import type {ConcreteInstaller} from './installer/concrete/types.js';
import type {EnvFile} from '@/env/EnvFile.js';

declare module '@/Context.ts' {
    interface Context {
        readonly installer: Installer;
        readonly docker: DockerContext;
    }
}

declare module '@/EventBus.ts' {
    interface AsyncEventTypes {
        'docker:up:before': { args: Set<string> };
        'installer:before': { installer: ConcreteInstaller };
        'installer:dependencies:before': undefined;
        'installer:loopbackIp:before': { ip: string };
        'installer:domain:before': { domain: string, ip: string };
        'installer:certificates:before': undefined;
        'installer:envFile:filter': { envFile: EnvFile };
        'installer:after': undefined;
    }
}
