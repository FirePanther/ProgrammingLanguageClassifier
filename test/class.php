new class {
	function __construct()
	{
		(static function() {
			var_dump($this);
		})();
	}
};