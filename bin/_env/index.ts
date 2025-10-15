import * as process from 'node:process';
import {Application} from '@/Application.js';

await (new Application()).run(process.argv);
