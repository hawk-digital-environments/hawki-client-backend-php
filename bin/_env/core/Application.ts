import {CommonUi} from './CommonUi.js';
import {EventBus} from './EventBus.js';
import {createPackageJson} from './PackageInfo.js';
import {createPaths} from './Paths.js';
import {createEnvFile} from './env/EnvFile.js';
import {createPlatform} from './Platform.js';
import {loadAddons} from './loadAddons.js';
import {createContext, extendContext} from './Context.js';
import {welcome} from '@/welcome.js';

export class Application {
    public async run(args: string[]) {
        const events = new EventBus();
        const ui = new CommonUi(events);
        try {
            const context = createContext(events, ui);
            extendContext(context, 'paths', createPaths());
            extendContext(context, 'platform', createPlatform());
            extendContext(context, 'pkg', createPackageJson(context.paths));
            await welcome(context);
            await loadAddons(context);
            extendContext(context, 'env', await createEnvFile(context));

            const {program, pkg} = context;

            program
                .name(pkg.name)
                .description(pkg.description)
                .version(pkg.version)
                .option('--bin-verbose', 'Verbosity of the bin/env bootstrap script')
                .showSuggestionAfterError(true)
                .helpCommand(true)
                .addHelpText('beforeAll', () => ui.renderHelpIntro())
                .configureHelp({
                    sortSubcommands: true
                })
            ;

            await events.trigger('commands:define', {program});

            if (args.length < 3) {
                program.help();
                process.exit(0);
            }

            await program.parseAsync(args);
        } catch (error) {
            await events.trigger('error:before', {error});
            console.error(ui.renderError(error));
            await events.trigger('error:after', {error});
            process.exit(1);
        }
    }
}
