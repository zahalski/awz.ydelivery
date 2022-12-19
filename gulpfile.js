/*
* npm init (инициализация проекта)
* npm install --global gulp (выполнить, если не установлен глобально)
* npm link gulp (линк в глобальный)
* */
const gulpfile = require('gulp');
const shell = require('gulp-shell');

gulpfile.task('up', () => {
    return gulpfile.src('/').pipe(shell([
            'python checkup.py'
        ],
        {cwd: __dirname+'/build'}));
});
gulpfile.task('build', () => {
    return gulpfile.src('/').pipe(shell([
            'python cp1251.py',
            'python updater.py'
        ],
        {cwd: __dirname+'/build'}));
});
gulpfile.task('lang', () => {
    return gulpfile.src('/').pipe(shell([
            'python lang.py'
        ],
        {cwd: __dirname+'/tests'}));
});
gulpfile.task('lang-d', () => {
    return gulpfile.src('/').pipe(shell([
            'python lang.py --dep'
        ],
        {cwd: __dirname+'/tests'}));
});