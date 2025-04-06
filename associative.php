<?php
/**
 * 把陣列改成物件，並且修改物件成員時也會改到原本的陣列內容。
 *
 * 假設使用者訪問 test.php??x=003&page=&other=qqqq ，
 * 且已執行 $Get = new Associative($_GET);
 * 那麼 $Get->x 是 '003' ； $Get->y 是 NULL ； $Get->page 是 '' ； $Get->other 是 'qqqq' 。（找得到的均是字串；不存在的則是 NULL）
 *
 * 而後可呼叫 convert 方法進行資料的轉譯和過濾。
 * 例如上例的情形再執行 $Get->convert(['x' => 'int', 'y' => 'bool', 'page' => 'int']);
 * 則可得到 $Get->x 是 3 （整數）； $Get->y 是 false （布林值）； $Get->page 是 0 （整數）； $Get->other 不存在（若嘗試存取，則會是 NULL ）。
 * 且 $_GET亦被改寫為 ['x' => 3, 'y' => FALSE, 'page' => 0]
 *
 * 這樣再去生成網址或 SQL 可較為安全。
 *
 * https://www.php.net/manual/zh/language.oop5.overloading.php
 */
class Associative {
	protected /*array*/ $target;
	protected /*bool*/ $by_reference;

	public function __construct(
	    array &$target,
	    bool $by_reference = false
	) {
	    if ($this->by_reference = $by_reference) {
	        $this->target = &$target;
	        return;
	    }

	    foreach ($target as $key => $value) {
	        if (is_array($value)) {
	            if (array_is_list($value))
	                $value = self::list_recursive($value);
	            else $value = new self($value);
	        }
	        $this->target[$key] = $value;
	    }
	}

	public function __get(
	    string $name
	) /*: mixed*/ {
	    return isset($this->target[$name]) ? $this->target[$name] : NULL;
	}

	public function __set(
	    string $name,
	    /*mixed*/ $value
	) : void {
	    $this->target[$name] = $value;
	}

	public function __iseet(
	    string $name
	) : bool {
	    return isset($this->target[$name]);
	}

	public function __unset(
	    string $name
	) : void {
	    unset($this->target[$name]);
	}

	/**
	 * 轉換、並只留下指定的鍵們，依照指定的順序。
	 *
	 * 下述值在 PHP 進行布林轉換時會變為 true ，但在本案將變為 false ：
	 * * 僅含有二個以上 '0' 的字串
	 * * 僅含有半形空格的字串
	 * * 僅含有全形空格（U+3000）的字串
	 * * 'false', 'null', 'no', 'undefined' 字串及其大小寫轉換
	 * https://www.php.net/manual/zh/function.boolval.php#example-5249
	 *
	 * PHP 物件的屬性與方法的命名空間是不同的，
	 * $assocObj->convert 和 $assocObj->convert([]) 之間不會衝突，
	 * 遇到 $_GET['convert'] 之類的情形也可放心。
	 */
	public function convert(
	    array $assoc = []
	) : array {
	    foreach ($assoc as $key => $type) {
	        $value = $origin = $this->target[$key] ?? NULL;
	        switch ($type) {
	            case '*':
	            case 'any': {
	                break;
	            }
	            case 'bool':
	            case 'boolean': {
	                if ($origin) {
	                    $value = !preg_match('/^(0+|\x20+|\x{3000}+)$/u', $origin)
	                        && !in_array(strtolower($origin), ['null', 'false', 'no', 'undefined']);
	                }
	                else $value = false;
	                break;
	            }
	            default: {
	                settype($value, $type);
	            }
	        }
	        $assoc[$key] = $value;
	    }
	    return $this->target = $assoc;
	}

	public function __toString(
	) : string {
	    return http_build_query($this->target);
	}

	public function build_query(
	    ?string $arg_separator = null,
	    int $encoding_type = PHP_QUERY_RFC1738
	) : string {
	    return http_build_query($this->target, '', $arg_separator, $encoding_type);
	}

	public function to_array(
	    array $keys = null
	) : array {
	    if (is_null($keys)) return $this->target;
	    $result = [];
	    foreach ($keys as $k) $result[$k] = $this->target[$k];
	    return $result;
	}

	public function empty(
	) : bool {
	    return empty($this->target) || !count($this->target);
	}

	public static function list_recursive(
	    array $list
	) : array {
	    if (! is_array($list[0])) return $list;
	    $new_list = [];
	    foreach ($list as $item)
	        $new_list[] = new self($item);
	    return $new_list;
	}
}
