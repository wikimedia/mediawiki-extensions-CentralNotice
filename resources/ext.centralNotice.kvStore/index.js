// FIXME: The creation of this object should deterministically happen
// in a one file and no where else.
// For now, the below line is duplicated in several leaf nodes of the dep tree.
mw.centralNotice = ( mw.centralNotice || {} );

// Public
mw.centralNotice.kvStore = require( './kvStore.js' );
