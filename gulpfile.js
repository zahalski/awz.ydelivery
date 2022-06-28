const gulpfile = require('gulp');
const shell = require('gulp-shell');

gulpfile.task('build', () => {
    return gulpfile.src('/').pipe(shell([
            'python cp1251.py',
            'python updater.py'
        ],
        {cwd: __dirname+'/build'}));
});