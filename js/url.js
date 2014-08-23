/**
* URL.js
* @copyright 2014 xobotyi
* @license   New BSD License
*/

var URL = function( param, param2 ){
	param = param || false;
	param2 = ( param2 === false ) ? false : true;
	
	this.urlEncode = param2;
	this.data = { scheme: false, user: false, pass: false, host: false, port: false, path: false, query: false, params: false, fragment: false };
	
	if( param && typeof( param ) == 'string' ){
		this.url = param;
		
		this.parse();
	} else if ( param && typeof( param ) == 'object' ){
		for(var key in param){
			if( this.data.hasOwnProperty( key ) ){
				if( param[ key ] || ( key == 'params' && typeof(param.params) == 'object' ) )
					this.data[ key ] = param[ key ];
			}
		}
		
		this.update();
	}
}

URL.prototype = {
	val: function( key, param, param2 ){		
		if( this.data.hasOwnProperty( key ) ){
			if( typeof( param ) == 'undefined' ){
				return this.data[ key ] ? this.data[ key ] : false;
			} else if( typeof( param ) != 'undefined' ){
				if( key == 'params' ){
					if( typeof( param ) == 'string' ){
						if( typeof( param2 ) != 'undefined' ){
							this.data[ key ][ param ] = param2;
						} else{
							return this.data[ key ][ param ] ? this.data[ key ][ param ] : false;
						}
					} else if( typeof( param ) == 'object' ){
						for( var keys in param ){
							if( typeof( param[ keys ] ) != 'undefined' && ( param[ keys ] == '' || param[ keys ] === false ) ){
								if( this.data[ key ].hasOwnProperty( keys ) ){
									delete this.data[ key ][ keys ];
								}
							} else{
								this.data[ key ][ keys ] = param[ keys ];
							}
						}
					}
				} else{
					this.data[ key ] = param;
				}
				
				return this.update();
			}
		} else
			return 'undefined';
	},
	
	parse: function(){
		this.res = /^(?:([a-z0-9_\-\.]+):\/\/)*(?:([a-z0-9_\-\.]+)(?:\:)*([a-z0-9_\-\.]+)*\@)*([a-z0-9][a-z0-9_\-\.]+)(?:\:([\d]+))*(?:\/([^?#]*))*(?:\?([^?#]*))*(?:\#([^?#]*))*/gi.exec( this.url );
		
		this.data.scheme = this.res[ 1 ] || false;
		this.data.user = this.res[ 2 ] || false;
		this.data.pass = this.res[ 3 ] || false;
		this.data.host = this.res[ 4 ] || false;
		this.data.port = this.res[ 5 ] || false;
		this.data.path = this.res[ 6 ] || false;
		this.data.query = this.res[ 7 ] || false;
		this.data.fragment = this.res[ 8 ] || false;
		
		if( this.data.query ){
			this.data.params = {};
			this.parts = this.data.query.split( '&' );
			for(  var i = 0; i < this.parts.length; i++ ){
				param = this.parts[ i ].split( '=' );
				this.data.params[ param[ 0 ] ] = decodeURIComponent( param[ 1 ] );
			}
		}
		
		delete this.res;	
		delete this.parts;	
	},

	update: function(){
		this.data.query = '';
		for( var key in this.data.params ){
			this.data.query += this.urlEncode ? key+'='+encodeURIComponent( this.data.params[ key ] )+'&' : key+'='+this.data.params[ key ]+'&';
		}
		
		if( this.data.query )
			this.data.query = this.data.query.slice( 0, -1 );
		
		this.url = '';
		this.url += ( this.data.scheme && this.data.host ) ? this.data.scheme+'://' : '';
		
		if( this.data.user && !this.data.pass )
			this.url += this.data.user+'@';
		else if( this.data.user && this.data.pass )
			this.url += this.data.user+':'+this.data.pass+'@';
		
		this.url += this.data.host ? this.data.host : '';
		this.url += ( this.data.port && this.data.host ) || ( this.data.port && this.data.path ) ? ':'+this.data.port : '';
		this.url += this.data.path ? '/'+this.data.path : '/';
		this.url += this.data.query ? '?'+this.data.query : '';
		this.url += this.data.fragment ? '#'+this.data.fragment : '';
		
		return this;
	},
	
	go: function(){
		if(!this.data.scheme && this.data.host)
			this.data.scheme = 'http';
		
		window.location.href = this.update().url;
	}
}

String.prototype.urlVal = function( key, param, param2 ){
	var url = new URL( this.valueOf() );
	
	if( typeof( param ) == 'undefined' ){
		result = url.val( key );
	} else if( key == 'params' && typeof( param ) != 'undefined' && typeof( param2 ) == 'undefined' ){
		result = url.val( key, param );
	} else{
		url.val( key, param, param2 );
		
		result = url.url;
	}
	
	delete url;
	
	return result;
}
String.prototype.go = function(){
	var url = new URL( this.valueOf() );
	
	url.go();
}