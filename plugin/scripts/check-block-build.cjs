const { readFileSync, statSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const blockDir = join( __dirname, '../build/hebrew-date' );
const metadata = JSON.parse(
	readFileSync( join( blockDir, 'block.json' ), 'utf8' )
);

for ( const field of [ 'editorScript', 'editorStyle', 'style', 'render' ] ) {
	const reference = metadata[ field ];
	if (
		! reference?.startsWith( 'file:' ) ||
		! statSync( join( blockDir, reference.slice( 5 ) ), {
			throwIfNoEntry: false,
		} )?.isFile()
	) {
		throw new Error(
			`Missing built block file for ${ field }: ${ reference }`
		);
	}
}

// WordPress needs the generated dependency manifest to load the editor script.
const manifest = metadata.editorScript
	.slice( 5 )
	.replace( /\.js$/, '.asset.php' );
if (
	! statSync( join( blockDir, manifest ), {
		throwIfNoEntry: false,
	} )?.isFile()
) {
	throw new Error( `Missing block dependency manifest: ${ manifest }` );
}
